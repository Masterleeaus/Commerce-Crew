<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Contracts\PaymentProvider;
use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\BnplOffer;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentIntent;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentOperation;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentRefund;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentWebhookEvent;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPayment;
use App\Extensions\ChatbotEcommerce\System\Support\BnplStatus;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutStatus;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use App\Extensions\ChatbotEcommerce\System\Support\PaymentLifecycle;
use App\Extensions\ChatbotEcommerce\System\Support\PaymentStatus;
use App\Extensions\ChatbotEcommerce\System\Support\PaymentWebhookVerifier;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PaymentRuntime
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly CheckoutRuntime $checkouts,
        private readonly RentalHireRuntime $rentalHire,
        private readonly NativeOrderRuntime $orders,
    ) {}

    public function createForCheckout(
        CheckoutSession $checkout,
        string $method,
        string $idempotencyKey,
        array $attributes = [],
    ): PaymentIntent {
        $this->assertProvider($attributes['provider'] ?? $this->provider->name());
        $this->assertMethod($method);
        $this->assertHostedActionAvailable($method, $attributes);
        $key = $this->requiredKey($idempotencyKey);
        $requestHash = $this->requestHash(['checkout', $checkout->uuid, $method, $attributes]);

        if ($existing = PaymentIntent::query()->where('idempotency_key', $key)->first()) {
            $this->assertCreateMatches($existing, $requestHash, 'checkout_session_id', (int) $checkout->id);
            return $this->hydrate($existing);
        }

        return DB::transaction(function () use ($checkout, $method, $key, $requestHash, $attributes): PaymentIntent {
            $locked = CheckoutSession::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== CheckoutStatus::PAYMENT_PENDING) {
                throw ValidationException::withMessages(['checkout' => 'Checkout must be payment pending before a payment intent can be created.']);
            }
            if ((int) $locked->total <= 0) {
                throw ValidationException::withMessages(['amount' => 'A payment intent requires a positive checkout total.']);
            }

            if ($locked->payment_intent_id) {
                $active = PaymentIntent::query()->whereKey($locked->payment_intent_id)->lockForUpdate()->first();
                if ($active && ! PaymentStatus::isTerminal((string) $active->status)) {
                    if ((string) $active->method !== strtolower(trim($method))) {
                        throw ValidationException::withMessages(['method' => 'An active payment intent already exists with another payment method.']);
                    }
                    return $this->hydrate($active);
                }
            }

            $result = $this->provider->createIntent([
                'scope' => 'checkout',
                'scope_uuid' => $locked->uuid,
                'method' => $method,
                'amount' => (int) $locked->total,
                'currency' => (string) $locked->currency,
                'payment_url' => $attributes['payment_url'] ?? null,
                'instructions' => $attributes['instructions'] ?? [],
                'metadata' => $attributes['metadata'] ?? [],
            ]);

            $intent = PaymentIntent::query()->create([
                'checkout_session_id' => $locked->id,
                'chatbot_id' => $locked->chatbot_id,
                'customer_identity_id' => $locked->customer_identity_id,
                'provider' => $this->provider->name(),
                'method' => strtolower(trim($method)),
                'status' => $this->normalStatus($result['status'] ?? PaymentStatus::PENDING),
                'amount' => (int) $locked->total,
                'currency' => strtoupper((string) $locked->currency),
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'provider_payment_id' => $result['provider_payment_id'] ?? null,
                'payment_url' => $result['payment_url'] ?? null,
                'instructions' => $result['instructions'] ?? [],
                'provider_payload' => $result['provider_payload'] ?? [],
                'metadata' => $attributes['metadata'] ?? [],
                'expires_at' => now()->addMinutes($this->intentTtlMinutes()),
            ]);

            $locked->forceFill(['payment_intent_id' => $intent->id, 'payment_status' => $intent->status])->save();
            Metrics::increment('payments.intent.created', ['scope' => 'checkout', 'provider' => $intent->provider]);
            event(new ExtensionEvent('payment.intent.created', ['payment_intent_uuid' => $intent->uuid, 'checkout_uuid' => $locked->uuid]));

            return $this->hydrate($intent);
        });
    }

    public function createForRentalPayment(
        RentalPayment $payment,
        string $method,
        string $idempotencyKey,
        array $attributes = [],
    ): PaymentIntent {
        $this->assertProvider($attributes['provider'] ?? $this->provider->name());
        $this->assertMethod($method);
        $this->assertHostedActionAvailable($method, $attributes);
        $key = $this->requiredKey($idempotencyKey);
        $requestHash = $this->requestHash(['rental_payment', $payment->uuid, $method, $attributes]);

        if ($existing = PaymentIntent::query()->where('idempotency_key', $key)->first()) {
            $this->assertCreateMatches($existing, $requestHash, 'rental_payment_id', (int) $payment->id);
            return $this->hydrate($existing);
        }

        return DB::transaction(function () use ($payment, $method, $key, $requestHash, $attributes): PaymentIntent {
            $locked = RentalPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Only a pending rental or hire payment request can create a payment intent.']);
            }
            if ($locked->expires_at && $locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['payment' => 'The rental or hire payment request has expired.']);
            }
            if ($locked->payment_intent_id) {
                $active = PaymentIntent::query()->whereKey($locked->payment_intent_id)->lockForUpdate()->first();
                if ($active && ! PaymentStatus::isTerminal((string) $active->status)) {
                    if ((string) $active->method !== strtolower(trim($method))) {
                        throw ValidationException::withMessages(['method' => 'An active payment intent already exists with another payment method.']);
                    }
                    return $this->hydrate($active);
                }
            }

            $result = $this->provider->createIntent([
                'scope' => 'rental_hire',
                'scope_uuid' => $locked->uuid,
                'method' => $method,
                'amount' => (int) $locked->amount,
                'currency' => (string) $locked->currency,
                'payment_url' => $attributes['payment_url'] ?? $locked->payment_url,
                'instructions' => array_replace_recursive((array) ($locked->instructions ?? []), (array) ($attributes['instructions'] ?? [])),
                'metadata' => $attributes['metadata'] ?? [],
            ]);

            $intent = PaymentIntent::query()->create([
                'rental_payment_id' => $locked->id,
                'customer_identity_id' => $locked->account()->value('customer_identity_id'),
                'chatbot_id' => $locked->account()->value('chatbot_id'),
                'provider' => $this->provider->name(),
                'method' => strtolower(trim($method)),
                'status' => $this->normalStatus($result['status'] ?? PaymentStatus::PENDING),
                'amount' => (int) $locked->amount,
                'currency' => strtoupper((string) $locked->currency),
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'provider_payment_id' => $result['provider_payment_id'] ?? null,
                'payment_url' => $result['payment_url'] ?? $locked->payment_url,
                'instructions' => $result['instructions'] ?? $locked->instructions ?? [],
                'provider_payload' => $result['provider_payload'] ?? [],
                'metadata' => $attributes['metadata'] ?? [],
                'expires_at' => $locked->expires_at ?: now()->addMinutes($this->intentTtlMinutes()),
            ]);

            $locked->forceFill([
                'payment_intent_id' => $intent->id,
                'provider' => $intent->provider,
                'provider_payment_id' => $intent->provider_payment_id,
                'payment_url' => $intent->payment_url,
                'instructions' => $intent->instructions,
            ])->save();

            Metrics::increment('payments.intent.created', ['scope' => 'rental_hire', 'provider' => $intent->provider]);
            event(new ExtensionEvent('payment.intent.created', ['payment_intent_uuid' => $intent->uuid, 'rental_payment_uuid' => $locked->uuid]));

            return $this->hydrate($intent);
        });
    }

    public function authorize(PaymentIntent $intent, ?int $amount, string $idempotencyKey): PaymentIntent
    {
        $amount ??= (int) $intent->amount;
        if ($amount <= 0 || $amount > (int) $intent->amount) {
            throw ValidationException::withMessages(['amount' => 'Authorization amount must be positive and cannot exceed the payment amount.']);
        }

        $updated = $this->operate($intent, 'authorize', $idempotencyKey, $amount, function (PaymentIntent $locked) use ($amount): array {
            if (! in_array((string) $locked->status, [PaymentStatus::PENDING, PaymentStatus::REQUIRES_ACTION], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment cannot be authorised from its current state.']);
            }
            return $this->provider->authorize($this->providerContext($locked, $amount));
        });

        return $this->hydrate($updated);
    }

    public function capture(PaymentIntent $intent, ?int $amount, string $idempotencyKey): PaymentIntent
    {
        $base = (int) ($intent->authorized_amount > 0 ? $intent->authorized_amount : $intent->amount);
        $amount ??= PaymentLifecycle::remainingCapture($base, (int) $intent->captured_amount);
        if ($amount <= 0 || $amount > PaymentLifecycle::remainingCapture($base, (int) $intent->captured_amount)) {
            throw ValidationException::withMessages(['amount' => 'Capture amount exceeds the remaining authorised payment amount.']);
        }

        $updated = $this->operate($intent, 'capture', $idempotencyKey, $amount, function (PaymentIntent $locked) use ($amount): array {
            if (! in_array((string) $locked->status, [PaymentStatus::PENDING, PaymentStatus::REQUIRES_ACTION, PaymentStatus::AUTHORIZED, PaymentStatus::PARTIALLY_CAPTURED], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment cannot be captured from its current state.']);
            }
            return $this->provider->capture($this->providerContext($locked, $amount));
        }, true);

        if ((string) $updated->status === PaymentStatus::CAPTURED) {
            $this->synchroniseCaptured($updated);
        }

        return $this->hydrate($updated->refresh());
    }

    public function cancel(PaymentIntent $intent, string $idempotencyKey, ?string $reason = null): PaymentIntent
    {
        $updated = $this->operate($intent, 'cancel', $idempotencyKey, null, function (PaymentIntent $locked) use ($reason): array {
            if (in_array((string) $locked->status, [PaymentStatus::PARTIALLY_CAPTURED, PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED], true)) {
                throw ValidationException::withMessages(['payment' => 'A captured payment cannot be cancelled; use a refund.']);
            }
            if (PaymentStatus::isTerminal((string) $locked->status)) {
                return ['status' => $locked->status];
            }
            return $this->provider->cancel($this->providerContext($locked, null, ['reason' => $reason]));
        });

        $this->synchroniseStatus($updated);
        return $this->hydrate($updated->refresh());
    }

    public function refund(PaymentIntent $intent, int $amount, string $idempotencyKey, ?string $reason = null): PaymentRefund
    {
        $key = $this->requiredKey($idempotencyKey);
        if ($existing = PaymentRefund::query()->where('idempotency_key', $key)->first()) {
            if ((int) $existing->payment_intent_id !== (int) $intent->id || (int) $existing->amount !== $amount) {
                throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for another refund.']);
            }
            return $existing->load('intent');
        }

        $refund = DB::transaction(function () use ($intent, $amount, $key, $reason): PaymentRefund {
            $locked = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if (! in_array((string) $locked->status, [PaymentStatus::PARTIALLY_CAPTURED, PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::DISPUTED], true)) {
                throw ValidationException::withMessages(['payment' => 'Only captured payments can be refunded.']);
            }
            $remaining = PaymentLifecycle::remainingRefund((int) $locked->captured_amount, (int) $locked->refunded_amount);
            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Refund amount exceeds the remaining refundable amount.']);
            }

            $result = $this->provider->refund($this->providerContext($locked, $amount, ['reason' => $reason]));
            $refund = PaymentRefund::query()->create([
                'payment_intent_id' => $locked->id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'status' => 'succeeded',
                'reason' => $reason,
                'idempotency_key' => $key,
                'provider_refund_id' => $result['provider_refund_id'] ?? null,
                'metadata' => ['provider_response' => $result],
                'processed_at' => now(),
            ]);

            $refunded = (int) $locked->refunded_amount + $amount;
            $nextStatus = PaymentLifecycle::refundStatus((int) $locked->captured_amount, $refunded);
            PaymentLifecycle::assertTransition((string) $locked->status, $nextStatus);
            $locked->forceFill([
                'refunded_amount' => $refunded,
                'status' => $nextStatus,
                'refunded_at' => $nextStatus === PaymentStatus::REFUNDED ? now() : $locked->refunded_at,
            ])->save();

            $this->updateScopeStatus($locked);
            Metrics::increment('payments.refunded', ['provider' => $locked->provider]);
            event(new ExtensionEvent('payment.refunded', ['payment_intent_uuid' => $locked->uuid, 'refund_uuid' => $refund->uuid, 'amount' => $amount]));
            return $refund;
        });

        $this->synchroniseRefund($refund->load('intent'));
        return $refund->fresh(['intent']);
    }

    public function reconcile(PaymentIntent $intent, string $idempotencyKey): PaymentIntent
    {
        $updated = $this->operate($intent, 'reconcile', $idempotencyKey, null, function (PaymentIntent $locked): array {
            return $this->provider->retrieve($this->providerContext($locked));
        }, true);

        if ((string) $updated->status === PaymentStatus::CAPTURED) {
            $this->synchroniseCaptured($updated);
        } else {
            $this->synchroniseStatus($updated);
        }

        return $this->hydrate($updated->refresh());
    }

    public function receiveWebhook(
        string $provider,
        string $rawPayload,
        array $payload,
        ?string $signature,
        ?string $timestamp,
    ): PaymentWebhookEvent {
        $this->assertProvider($provider);
        $parsed = $this->provider->parseWebhook($payload);
        $eventId = trim((string) ($parsed['event_id'] ?? '')) ?: hash('sha256', $rawPayload);
        $eventKey = PaymentWebhookVerifier::eventKey($provider, $eventId, $rawPayload);
        $valid = $this->provider->verifyWebhook($rawPayload, $signature, $timestamp);

        $event = PaymentWebhookEvent::query()->where('event_key', $eventKey)->first();
        if ($event && (string) $event->status === 'processed') {
            return $event->load('intent');
        }

        $intent = $valid ? $this->findWebhookIntent($provider, $parsed) : null;
        $attributes = [
            'payment_intent_id' => $intent?->id,
            'provider' => $provider,
            'event_id' => $eventId,
            'event_key' => $eventKey,
            'event_type' => $parsed['event_type'] ?? null,
            'payload_hash' => hash('sha256', $rawPayload),
            'signature_valid' => $valid,
            'status' => $valid ? 'received' : 'rejected',
            'payload' => $payload,
            'error_message' => $valid ? null : 'Invalid webhook signature.',
            'received_at' => now(),
            'next_attempt_at' => null,
            'dead_lettered_at' => null,
        ];

        if ($event) {
            $event->forceFill($attributes)->save();
        } else {
            $event = PaymentWebhookEvent::query()->create($attributes + ['attempt_count' => 0]);
        }

        if (! $valid) {
            Metrics::increment('payments.webhook.rejected', ['provider' => $provider]);
            throw ValidationException::withMessages(['signature' => 'The payment webhook signature is invalid or stale.']);
        }

        return $event->fresh();
    }

    public function processReceivedWebhook(PaymentWebhookEvent $event): PaymentWebhookEvent
    {
        if ((string) $event->status === 'processed') return $event->load('intent');
        if (! $event->signature_valid || in_array((string) $event->status, ['rejected', 'dead_lettered'], true)) {
            throw ValidationException::withMessages(['webhook' => 'The payment webhook is not eligible for processing.']);
        }

        $maxAttempts = max(1, (int) config('chatbot-ecommerce.reliability.webhooks.max_attempts', 5));
        $event = DB::transaction(function () use ($event): PaymentWebhookEvent {
            $locked = PaymentWebhookEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === 'processed') return $locked;
            $locked->forceFill([
                'status' => 'processing',
                'attempt_count' => (int) $locked->attempt_count + 1,
                'processing_started_at' => now(),
                'last_attempt_at' => now(),
                'next_attempt_at' => null,
                'error_message' => null,
            ])->save();
            return $locked;
        });

        try {
            $payload = (array) ($event->payload ?? []);
            $parsed = $this->provider->parseWebhook($payload);
            $intent = $this->findWebhookIntent((string) $event->provider, $parsed);
            if (! $intent) {
                throw ValidationException::withMessages(['payment' => 'The webhook does not reference a known payment intent.']);
            }

            $updated = DB::transaction(function () use ($intent, $parsed, $event): PaymentIntent {
                $locked = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
                $this->applyProviderState($locked, $parsed);
                $this->updateScopeStatus($locked);
                PaymentWebhookEvent::query()->whereKey($event->id)->update([
                    'payment_intent_id' => $locked->id,
                    'status' => 'processed',
                    'processed_at' => now(),
                    'next_attempt_at' => null,
                    'error_message' => null,
                ]);
                return $locked;
            });

            if ((string) $updated->status === PaymentStatus::CAPTURED) $this->synchroniseCaptured($updated);
            else $this->synchroniseStatus($updated);

            Metrics::increment('payments.webhook.processed', ['provider' => (string) $event->provider]);
            return PaymentWebhookEvent::query()->with('intent')->findOrFail($event->id);
        } catch (Throwable $exception) {
            $attempts = (int) $event->attempt_count;
            $deadLettered = $attempts >= $maxAttempts;
            $delay = \App\Extensions\ChatbotEcommerce\System\Support\RetryBackoff::seconds(
                max(1, $attempts),
                (int) config('chatbot-ecommerce.reliability.webhooks.base_backoff_seconds', 5),
                (int) config('chatbot-ecommerce.reliability.webhooks.maximum_backoff_seconds', 300),
            );
            PaymentWebhookEvent::query()->whereKey($event->id)->update([
                'status' => $deadLettered ? 'dead_lettered' : 'failed',
                'dead_lettered_at' => $deadLettered ? now() : null,
                'next_attempt_at' => $deadLettered ? null : now()->addSeconds($delay),
                'error_message' => substr($exception->getMessage(), 0, 2000),
            ]);
            Metrics::increment($deadLettered ? 'payments.webhook.dead_lettered' : 'payments.webhook.failed', ['provider' => (string) $event->provider]);
            throw $exception;
        }
    }

    public function processWebhook(
        string $provider,
        string $rawPayload,
        array $payload,
        ?string $signature,
        ?string $timestamp,
    ): PaymentWebhookEvent {
        return $this->processReceivedWebhook($this->receiveWebhook($provider, $rawPayload, $payload, $signature, $timestamp));
    }

    public function expireDue(int $limit = 500): int
    {
        $ids = PaymentIntent::query()
            ->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::REQUIRES_ACTION, PaymentStatus::AUTHORIZED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            $intent = DB::transaction(function () use ($id): ?PaymentIntent {
                $locked = PaymentIntent::query()->whereKey($id)->lockForUpdate()->first();
                if (! $locked || ! in_array((string) $locked->status, [PaymentStatus::PENDING, PaymentStatus::REQUIRES_ACTION, PaymentStatus::AUTHORIZED], true)) {
                    return null;
                }
                PaymentLifecycle::assertTransition((string) $locked->status, PaymentStatus::EXPIRED);
                $locked->forceFill(['status' => PaymentStatus::EXPIRED])->save();
                $this->updateScopeStatus($locked);
                return $locked;
            });
            if ($intent) {
                $this->synchroniseStatus($intent);
                $expired++;
            }
        }
        return $expired;
    }

    private function operate(
        PaymentIntent $intent,
        string $operation,
        string $idempotencyKey,
        ?int $amount,
        Closure $callback,
        bool $applyAmounts = false,
    ): PaymentIntent {
        $key = $this->requiredKey($idempotencyKey);
        $requestHash = $this->requestHash([$operation, $amount]);
        $existing = PaymentOperation::query()->where('payment_intent_id', $intent->id)->where('idempotency_key', $key)->first();
        if ($existing) {
            if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was reused with different payment operation data.']);
            }
            return $this->hydrate(PaymentIntent::query()->findOrFail($intent->id));
        }

        return DB::transaction(function () use ($intent, $operation, $key, $requestHash, $amount, $callback, $applyAmounts): PaymentIntent {
            $locked = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            $result = $callback($locked);
            $this->applyProviderState($locked, $result, $operation, $amount, $applyAmounts);
            PaymentOperation::query()->create([
                'payment_intent_id' => $locked->id,
                'operation' => $operation,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'amount' => $amount,
                'status' => 'succeeded',
                'provider_operation_id' => $result['provider_operation_id'] ?? null,
                'response' => $result,
            ]);
            $this->updateScopeStatus($locked);
            Metrics::increment('payments.operation.' . $operation, ['provider' => $locked->provider]);
            event(new ExtensionEvent('payment.' . $operation, ['payment_intent_uuid' => $locked->uuid, 'amount' => $amount]));
            return $locked;
        });
    }

    private function applyProviderState(
        PaymentIntent $intent,
        array $result,
        ?string $operation = null,
        ?int $operationAmount = null,
        bool $applyAmounts = false,
    ): void {
        $nextStatus = $this->normalStatus($result['status'] ?? $intent->status);
        PaymentLifecycle::assertTransition((string) $intent->status, $nextStatus);

        $values = ['status' => $nextStatus];
        if ($operation === 'authorize') {
            $values['authorized_amount'] = max((int) $intent->authorized_amount, (int) ($result['authorized_amount'] ?? $operationAmount ?? 0));
            $values['authorized_at'] = now();
        }
        if ($operation === 'capture') {
            $values['captured_amount'] = min((int) $intent->amount, (int) $intent->captured_amount + (int) ($result['captured_amount'] ?? $operationAmount ?? 0));
            $values['captured_at'] = now();
        }
        if ($applyAmounts) {
            foreach (['authorized_amount', 'captured_amount', 'refunded_amount'] as $field) {
                if (array_key_exists($field, $result) && $result[$field] !== null) {
                    $values[$field] = max(0, (int) $result[$field]);
                }
            }
        }
        if ($nextStatus === PaymentStatus::CAPTURED && ! isset($values['captured_at'])) {
            $values['captured_at'] = $intent->captured_at ?: now();
            $values['captured_amount'] = max((int) ($values['captured_amount'] ?? 0), (int) ($result['captured_amount'] ?? $intent->amount));
        }
        if ($nextStatus === PaymentStatus::FAILED) {
            $values['failed_at'] = now();
            $values['failure_code'] = $result['failure_code'] ?? 'provider_failed';
            $values['failure_message'] = $result['failure_message'] ?? null;
        }
        if ($nextStatus === PaymentStatus::CANCELLED) {
            $values['cancelled_at'] = now();
        }
        $values['provider_payload'] = array_replace_recursive((array) ($intent->provider_payload ?? []), ['last_response' => $result]);
        $intent->forceFill($values)->save();
    }

    private function synchroniseCaptured(PaymentIntent $intent): void
    {
        if ($intent->checkout_session_id) {
            $checkout = CheckoutSession::query()->find($intent->checkout_session_id);
            if ($checkout && (string) $checkout->status === CheckoutStatus::PAYMENT_PENDING) {
                $reference = $checkout->order_reference ?: 'PAY-' . strtoupper(substr(str_replace('-', '', $intent->uuid), 0, 12));
                $completed = $this->checkouts->markCompleted($checkout, 'payment-capture:' . $intent->uuid, $reference);
                $this->orders->createFromCheckout($completed);
            }
        }

        if ($intent->rental_payment_id) {
            $payment = RentalPayment::query()->find($intent->rental_payment_id);
            if ($payment && (string) $payment->status === 'pending') {
                $this->rentalHire->confirmPayment($payment, 'payment-capture:' . $intent->uuid, [
                    'provider' => $intent->provider,
                    'provider_payment_id' => $intent->provider_payment_id,
                    'received_at' => $intent->captured_at ?: now(),
                    'auto_allocate' => true,
                ]);
            }
        }
    }

    private function synchroniseRefund(PaymentRefund $refund): void
    {
        $intent = $refund->intent;
        if (! $intent || ! $intent->rental_payment_id) {
            return;
        }
        $payment = RentalPayment::query()->find($intent->rental_payment_id);
        if (! $payment) {
            return;
        }
        $this->rentalHire->createAdjustment(
            $payment->account()->firstOrFail(),
            'debit',
            (int) $refund->amount,
            'Refund of rental/hire payment ' . $payment->payment_reference,
            null,
            'payment-refund:' . $refund->uuid,
            ['payment_intent_uuid' => $intent->uuid, 'refund_uuid' => $refund->uuid],
        );
    }

    private function synchroniseStatus(PaymentIntent $intent): void
    {
        if ($intent->checkout_session_id && in_array((string) $intent->status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED], true)) {
            $checkout = CheckoutSession::query()->find($intent->checkout_session_id);
            if ($checkout && ! CheckoutStatus::isTerminal((string) $checkout->status)) {
                $this->checkouts->cancel($checkout, 'payment_' . $intent->status, 'payment-status:' . $intent->uuid . ':' . $intent->status);
            }
        }
    }

    private function updateScopeStatus(PaymentIntent $intent): void
    {
        $this->synchroniseBnplOffer($intent);
        if ($intent->checkout_session_id) {
            CheckoutSession::query()->whereKey($intent->checkout_session_id)->update(['payment_status' => $intent->status, 'updated_at' => now()]);
        }
        if ($intent->rental_payment_id && in_array((string) $intent->status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED], true)) {
            RentalPayment::query()->whereKey($intent->rental_payment_id)->where('status', 'pending')->update([
                'status' => 'failed',
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function synchroniseBnplOffer(PaymentIntent $intent): void
    {
        $offer = BnplOffer::query()->where('payment_intent_id', $intent->id)->first();
        if (! $offer) {
            return;
        }

        $status = match ((string) $intent->status) {
            PaymentStatus::REQUIRES_ACTION, PaymentStatus::PENDING => BnplStatus::REQUIRES_ACTION,
            PaymentStatus::AUTHORIZED => BnplStatus::APPROVED,
            PaymentStatus::CAPTURED => BnplStatus::CAPTURED,
            PaymentStatus::PARTIALLY_REFUNDED => BnplStatus::PARTIALLY_REFUNDED,
            PaymentStatus::REFUNDED => BnplStatus::REFUNDED,
            PaymentStatus::FAILED, PaymentStatus::DISPUTED => BnplStatus::DECLINED,
            PaymentStatus::CANCELLED => BnplStatus::CANCELLED,
            PaymentStatus::EXPIRED => BnplStatus::EXPIRED,
            default => (string) $offer->status,
        };

        $values = ['status' => $status, 'provider_reference' => $intent->provider_payment_id];
        if ($status === BnplStatus::APPROVED) {
            $values['approved_at'] = $offer->approved_at ?: now();
        }
        if ($status === BnplStatus::CAPTURED) {
            $values['captured_at'] = $offer->captured_at ?: now();
        }
        if ($status === BnplStatus::DECLINED) {
            $values['declined_at'] = $offer->declined_at ?: now();
        }
        if ($status === BnplStatus::CANCELLED) {
            $values['cancelled_at'] = $offer->cancelled_at ?: now();
        }
        if ($status === BnplStatus::REFUNDED) {
            $values['refunded_at'] = $offer->refunded_at ?: now();
        }

        $offer->forceFill($values)->save();
    }

    private function findWebhookIntent(string $provider, array $parsed): ?PaymentIntent
    {
        if (! empty($parsed['payment_intent_uuid'])) {
            return PaymentIntent::query()->where('uuid', $parsed['payment_intent_uuid'])->where('provider', $provider)->first();
        }
        if (! empty($parsed['provider_payment_id'])) {
            return PaymentIntent::query()->where('provider_payment_id', $parsed['provider_payment_id'])->where('provider', $provider)->first();
        }
        return null;
    }

    private function providerContext(PaymentIntent $intent, ?int $amount = null, array $extra = []): array
    {
        return array_replace_recursive([
            'payment_intent_uuid' => $intent->uuid,
            'provider_payment_id' => $intent->provider_payment_id,
            'method' => $intent->method,
            'status' => $intent->status,
            'amount' => $amount,
            'total_amount' => (int) $intent->amount,
            'authorized_amount' => (int) $intent->authorized_amount,
            'captured_amount' => (int) $intent->captured_amount,
            'refunded_amount' => (int) $intent->refunded_amount,
            'currency' => $intent->currency,
            'metadata' => $intent->metadata ?? [],
        ], $extra);
    }

    private function hydrate(PaymentIntent $intent): PaymentIntent
    {
        return $intent->load(['checkout', 'rentalPayment', 'refunds', 'bnplOffer.providerProfile']);
    }

    private function assertMethod(string $method): void
    {
        if (! $this->provider->supports($method)) {
            throw ValidationException::withMessages(['method' => 'The selected payment method is not supported by the configured payment provider.']);
        }
    }


    private function assertHostedActionAvailable(string $method, array $attributes): void
    {
        $method = strtolower(trim($method));
        if ($this->provider->name() === 'internal' && in_array($method, ['card', 'direct_debit', 'bnpl'], true) && empty($attributes['payment_url'])) {
            throw ValidationException::withMessages(['payment_url' => 'The internal provider requires a hosted payment URL for card, direct-debit, or BNPL payments.']);
        }
    }

    private function assertProvider(mixed $provider): void
    {
        if (strtolower(trim((string) $provider)) !== $this->provider->name()) {
            throw ValidationException::withMessages(['provider' => 'The selected payment provider is not registered.']);
        }
    }

    private function assertCreateMatches(PaymentIntent $intent, string $requestHash, string $scopeField, int $scopeId): void
    {
        if ((int) $intent->getAttribute($scopeField) !== $scopeId || ! hash_equals((string) $intent->request_hash, $requestHash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for another payment intent.']);
        }
    }

    private function normalStatus(mixed $status): string
    {
        $status = strtolower(trim((string) $status));
        $allowed = [
            PaymentStatus::PENDING, PaymentStatus::REQUIRES_ACTION, PaymentStatus::AUTHORIZED, PaymentStatus::PARTIALLY_CAPTURED,
            PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED,
            PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED, PaymentStatus::DISPUTED,
        ];
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['provider_status' => 'The payment provider returned an unsupported status.']);
        }
        return $status;
    }

    private function requiredKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'The Idempotency-Key header is required.']);
        }
        return substr($key, 0, 191);
    }

    private function requestHash(array $data): string
    {
        return hash('sha256', json_encode($this->canonicalise($data), JSON_THROW_ON_ERROR));
    }

    private function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalise($item);
        }
        return $value;
    }

    private function intentTtlMinutes(): int
    {
        return min(max((int) config('chatbot-ecommerce.payments.intent_ttl_minutes', 30), 5), 10080);
    }
}
