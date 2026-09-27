<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Providers;

use App\Extensions\ChatbotEcommerce\System\Contracts\PaymentProvider;
use App\Extensions\ChatbotEcommerce\System\Support\PaymentStatus;
use App\Extensions\ChatbotEcommerce\System\Support\PaymentWebhookVerifier;
use Illuminate\Support\Str;

final class InternalPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'internal';
    }

    public function supports(string $method): bool
    {
        return in_array(strtolower(trim($method)), array_map('strval', (array) config('chatbot-ecommerce.payments.allowed_methods', [])), true);
    }

    public function createIntent(array $context): array
    {
        $method = strtolower((string) ($context['method'] ?? 'external'));
        $requiresAction = in_array($method, ['bank_transfer', 'payid', 'cash', 'external', 'card', 'direct_debit', 'bnpl'], true);
        if (in_array($method, ['card', 'direct_debit', 'bnpl'], true) && empty($context['payment_url'])) {
            throw new \InvalidArgumentException('The internal provider requires a hosted payment URL for card, direct-debit, or BNPL methods.');
        }

        return [
            'status' => $requiresAction ? PaymentStatus::REQUIRES_ACTION : PaymentStatus::PENDING,
            'provider_payment_id' => 'internal_' . Str::lower((string) Str::uuid()),
            'payment_url' => $context['payment_url'] ?? null,
            'instructions' => array_replace_recursive(
                (array) config('chatbot-ecommerce.payments.instructions.' . $method, []),
                (array) ($context['instructions'] ?? []),
            ),
            'provider_payload' => ['mode' => 'internal', 'method' => $method],
        ];
    }

    public function authorize(array $context): array
    {
        return [
            'status' => PaymentStatus::AUTHORIZED,
            'authorized_amount' => (int) ($context['amount'] ?? 0),
            'provider_operation_id' => 'auth_' . Str::lower((string) Str::uuid()),
        ];
    }

    public function capture(array $context): array
    {
        $amount = (int) ($context['amount'] ?? 0);
        $captured = (int) ($context['captured_amount'] ?? 0) + $amount;
        $ceiling = (int) (($context['authorized_amount'] ?? 0) ?: ($context['total_amount'] ?? 0));
        return [
            'status' => $captured < $ceiling ? PaymentStatus::PARTIALLY_CAPTURED : PaymentStatus::CAPTURED,
            'captured_amount' => $amount,
            'provider_operation_id' => 'cap_' . Str::lower((string) Str::uuid()),
        ];
    }

    public function cancel(array $context): array
    {
        return [
            'status' => PaymentStatus::CANCELLED,
            'provider_operation_id' => 'can_' . Str::lower((string) Str::uuid()),
        ];
    }

    public function refund(array $context): array
    {
        return [
            'status' => PaymentStatus::REFUNDED,
            'refunded_amount' => (int) ($context['amount'] ?? 0),
            'provider_refund_id' => 'ref_' . Str::lower((string) Str::uuid()),
        ];
    }

    public function retrieve(array $context): array
    {
        return [
            'status' => (string) ($context['status'] ?? PaymentStatus::PENDING),
            'authorized_amount' => (int) ($context['authorized_amount'] ?? 0),
            'captured_amount' => (int) ($context['captured_amount'] ?? 0),
            'refunded_amount' => (int) ($context['refunded_amount'] ?? 0),
            'provider_payment_id' => $context['provider_payment_id'] ?? null,
        ];
    }

    public function verifyWebhook(string $payload, ?string $signature, ?string $timestamp): bool
    {
        if (! is_string($timestamp) || ! ctype_digit($timestamp)) {
            return false;
        }

        return PaymentWebhookVerifier::verify(
            $payload,
            $signature,
            (string) config('chatbot-ecommerce.payments.providers.internal.webhook_secret', ''),
            (int) $timestamp,
            time(),
            (int) config('chatbot-ecommerce.payments.webhook_tolerance_seconds', 300),
        );
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'event_id' => (string) ($payload['id'] ?? $payload['event_id'] ?? ''),
            'event_type' => (string) ($payload['event'] ?? $payload['type'] ?? ''),
            'payment_intent_uuid' => $payload['payment_intent_uuid'] ?? null,
            'provider_payment_id' => $payload['provider_payment_id'] ?? null,
            'status' => $payload['status'] ?? null,
            'authorized_amount' => isset($payload['authorized_amount']) ? (int) $payload['authorized_amount'] : null,
            'captured_amount' => isset($payload['captured_amount']) ? (int) $payload['captured_amount'] : null,
            'refunded_amount' => isset($payload['refunded_amount']) ? (int) $payload['refunded_amount'] : null,
            'failure_code' => $payload['failure_code'] ?? null,
            'failure_message' => $payload['failure_message'] ?? null,
        ];
    }
}
