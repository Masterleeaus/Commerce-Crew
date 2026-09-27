<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutOperation;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryReservation;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;
use App\Extensions\ChatbotEcommerce\System\Support\CartStatus;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutAddress;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutStatus;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryReservationState;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CheckoutRuntime
{
    public function __construct(
        private readonly CartRuntime $carts,
        private readonly InventoryRuntime $inventory,
        private readonly PricingRuntime $pricing,
        private readonly ShippingRuntime $shipping,
    ) {}

    public function getOrCreate(ChatbotCart $cart, ?string $idempotencyKey = null, array $context = []): CheckoutSession
    {
        $activeKey = hash('sha256', 'checkout|' . $cart->id);

        try {
            return DB::transaction(function () use ($cart, $idempotencyKey, $context, $activeKey): CheckoutSession {
                $lockedCart = ChatbotCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $this->assertCartAvailable($lockedCart, false);

            $checkout = CheckoutSession::query()->where('active_key', $activeKey)->lockForUpdate()->first();
            if ($checkout && ($checkout->expires_at?->isPast() ?? false)) {
                $this->expireLocked($checkout, 'expired_on_access');
                $checkout = null;
            }

            if ($checkout) {
                return $this->hydrate($checkout);
            }

            $checkout = CheckoutSession::query()->create([
                'uuid' => (string) Str::uuid(),
                'cart_id' => $lockedCart->id,
                'chatbot_id' => $lockedCart->chatbot_id,
                'session_id' => $lockedCart->session_id,
                'conversation_id' => $lockedCart->conversation_id,
                'customer_identity_id' => $lockedCart->customer_identity_id,
                'status' => CheckoutStatus::DRAFT,
                'active_key' => $activeKey,
                'currency' => $lockedCart->currency ?: 'USD',
                'cart_version' => (int) $lockedCart->version,
                'pricing_snapshot_uuid' => $lockedCart->pricing_snapshot_uuid,
                'pricing_snapshot_hash' => $lockedCart->pricing_snapshot_hash,
                'pricing_snapshot' => $lockedCart->price_snapshot ?? [],
                'subtotal' => (int) $lockedCart->subtotal,
                'discount_total' => (int) $lockedCart->discount_total,
                'tax_total' => (int) $lockedCart->tax_total,
                'tax_zone_id' => $lockedCart->tax_zone_id,
                'tax_context_hash' => $lockedCart->tax_context_hash,
                'tax_breakdown' => $lockedCart->tax_breakdown ?? [],
                'tax_exemption_key' => ($lockedCart->metadata ?? [])['tax_exemption_key'] ?? null,
                'shipping_total' => (int) $lockedCart->shipping_total,
                'total' => (int) $lockedCart->total,
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
                'metadata' => array_replace_recursive($context['metadata'] ?? [], [
                    'creation_idempotency_key' => $idempotencyKey,
                ]),
            ]);

            Metrics::increment('checkout.created');
            event(new ExtensionEvent('checkout.created', [
                'checkout_uuid' => $checkout->uuid,
                'cart_uuid' => $lockedCart->uuid,
            ]));

                return $this->hydrate($checkout);
            });
        } catch (QueryException $exception) {
            $existing = CheckoutSession::query()->where('active_key', $activeKey)->first();
            if ($existing) {
                return $this->hydrate($existing);
            }
            throw $exception;
        }
    }

    public function updateCustomer(CheckoutSession $checkout, array $data, ?string $idempotencyKey = null): CheckoutSession
    {
        $response = $this->operate($checkout, $idempotencyKey, 'customer.update', $data, function (CheckoutSession $locked) use ($data): array {
            $this->assertMutable($locked);

            $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
            $phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
            if (($email === null || $email === '') && ($phone === null || $phone === '')) {
                throw ValidationException::withMessages(['customer' => 'An email address or phone number is required.']);
            }

            $billing = CheckoutAddress::normalise((array) ($data['billing_address'] ?? []));
            $shipping = CheckoutAddress::normalise((array) ($data['shipping_address'] ?? []));
            if (array_filter($billing, static fn ($value): bool => $value !== null) !== [] && ! CheckoutAddress::isComplete($billing)) {
                throw ValidationException::withMessages(['billing_address' => 'The billing address is incomplete.']);
            }

            $targetStatus = $locked->delivery_method ? CheckoutStatus::DELIVERY_SELECTED : CheckoutStatus::CUSTOMER_DETAILS;
            $this->transition($locked, $targetStatus);
            $locked->forceFill([
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'billing_address' => $billing,
                'shipping_address' => $shipping,
                'customer_note' => isset($data['customer_note']) ? trim((string) $data['customer_note']) : $locked->customer_note,
                'consent' => (array) ($data['consent'] ?? $locked->consent ?? []),
                'tax_exemption_key' => isset($data['tax_exemption_key']) ? trim((string) $data['tax_exemption_key']) : $locked->tax_exemption_key,
                'approval_token_hash' => null,
                'approval_expires_at' => null,
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
            ])->save();

            $cart = ChatbotCart::query()->whereKey($locked->cart_id)->lockForUpdate()->firstOrFail();
            $taxContextChanged = $this->syncTaxContextToCart($locked, $cart);
            if ($taxContextChanged) {
                $cart = $this->carts->recalculate($cart, 'checkout:' . $locked->uuid . ':v' . $cart->version . ':tax-context:' . hash('sha256', json_encode([
                    $locked->billing_address,
                    $locked->shipping_address,
                    $locked->tax_exemption_key,
                ], JSON_THROW_ON_ERROR)));
            }
            $this->syncCartSnapshot($locked, $cart);

            Metrics::increment('checkout.customer_updated');
            event(new ExtensionEvent('checkout.customer_updated', ['checkout_uuid' => $locked->uuid]));

            return [];
        });

        return $this->checkoutFromResponse($response);
    }

    public function selectDelivery(CheckoutSession $checkout, array $delivery, ?string $idempotencyKey = null): CheckoutSession
    {
        $response = $this->operate($checkout, $idempotencyKey, 'delivery.select', $delivery, function (CheckoutSession $locked) use ($delivery): array {
            $this->assertMutable($locked);
            if (! in_array((string) $locked->status, [CheckoutStatus::CUSTOMER_DETAILS, CheckoutStatus::DELIVERY_SELECTED, CheckoutStatus::READY, CheckoutStatus::REQUIRES_REVIEW], true)) {
                throw ValidationException::withMessages(['checkout' => 'Customer details must be supplied before delivery is selected.']);
            }

            $method = strtolower(trim((string) ($delivery['method'] ?? '')));
            if (! in_array($method, ['delivery', 'pickup', 'digital'], true)) {
                throw ValidationException::withMessages(['method' => 'Delivery method must be delivery, pickup, or digital.']);
            }
            if ($method === 'delivery' && ! CheckoutAddress::isComplete((array) $locked->shipping_address)) {
                throw ValidationException::withMessages(['shipping_address' => 'A complete shipping address is required for delivery.']);
            }

            $cart = ChatbotCart::query()->whereKey($locked->cart_id)->lockForUpdate()->firstOrFail();
            $this->assertCartAvailable($cart, true);
            $code = trim((string) ($delivery['code'] ?? $method));
            $quote = collect($this->shipping->quoteForCheckout($locked, $cart))
                ->first(fn ($candidate): bool => (string) $candidate->code === $code && (string) $candidate->method_type === $method);
            if (! $quote && $method === 'digital' && $this->shipping->requiresDelivery($cart)) {
                throw ValidationException::withMessages(['method' => 'Digital delivery cannot be selected for a cart containing physical products.']);
            }
            $resolvedDelivery = $quote
                ? $this->shipping->payload($quote)
                : $this->resolveDeliveryOption($method, $delivery);
            $amount = (int) $resolvedDelivery['amount'];
            $cart->forceFill([
                'shipping_subtotal' => $amount,
                'shipping_total' => $amount,
                'last_activity_at' => now(),
            ])->save();
            $this->syncTaxContextToCart($locked, $cart);
            $cart = $this->carts->recalculate($cart, 'checkout:' . $locked->uuid . ':v' . $cart->version . ':delivery:' . hash('sha256', json_encode($delivery, JSON_THROW_ON_ERROR)));
            if ($quote) {
                $quote = $this->shipping->selectQuote($quote, $locked, $cart);
                $resolvedDelivery = $this->shipping->payload($quote);
            }

            $this->transition($locked, CheckoutStatus::DELIVERY_SELECTED);
            $locked->forceFill([
                'shipping_quote_id' => $quote?->id,
                'delivery_method' => $method,
                'delivery_option' => $resolvedDelivery,
                'approval_token_hash' => null,
                'approval_expires_at' => null,
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
            ])->save();
            $this->syncCartSnapshot($locked, $cart);

            Metrics::increment('checkout.delivery_selected', ['method' => $method]);
            event(new ExtensionEvent('checkout.delivery_selected', [
                'checkout_uuid' => $locked->uuid,
                'method' => $method,
                'amount' => $amount,
            ]));

            return [];
        });

        return $this->checkoutFromResponse($response);
    }

    /** @return array{checkout: CheckoutSession, approval_token: string} */
    public function prepare(
        CheckoutSession $checkout,
        ?int $expectedCartVersion,
        ?string $expectedPricingHash,
        string $idempotencyKey,
    ): array {
        $key = $this->requiredKey($idempotencyKey);
        $response = $this->operate($checkout, $key, 'checkout.prepare', [
            'expected_cart_version' => $expectedCartVersion,
            'expected_pricing_hash' => $expectedPricingHash,
        ], function (CheckoutSession $locked) use ($expectedCartVersion, $expectedPricingHash): array {
            $this->assertMutable($locked);
            $this->assertCustomerAndDeliveryComplete($locked);

            $cart = ChatbotCart::query()->whereKey($locked->cart_id)->lockForUpdate()->firstOrFail();
            $this->assertCartAvailable($cart, true);
            if ($expectedCartVersion !== null && (int) $cart->version !== $expectedCartVersion) {
                throw ValidationException::withMessages(['cart_version' => 'The cart changed before checkout preparation.']);
            }
            if ($expectedPricingHash !== null && ! hash_equals((string) $cart->pricing_snapshot_hash, $expectedPricingHash)) {
                throw ValidationException::withMessages(['pricing_snapshot_hash' => 'The cart pricing changed before checkout preparation.']);
            }
            if ($locked->shipping_quote_id !== null) {
                $this->shipping->assertQuoteStillValid($locked, $cart);
            }

            $this->syncTaxContextToCart($locked, $cart);
            $cart = $this->carts->recalculate($cart, 'checkout:' . $locked->uuid . ':prepare:v' . $cart->version);
            if ($locked->shipping_quote_id !== null) {
                $this->shipping->refreshSelectedQuote($locked, $cart);
            }
            $inventorySnapshot = $this->reserveCartInventory($locked, $cart);
            $token = Str::random(64);

            $this->transition($locked, CheckoutStatus::READY);
            $locked->forceFill([
                'approval_token_hash' => hash('sha256', $token),
                'approval_expires_at' => now()->addMinutes($this->approvalTtlMinutes()),
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
                'inventory_snapshot' => $inventorySnapshot,
                'failure_code' => null,
            ])->save();
            $this->syncCartSnapshot($locked, $cart);

            Metrics::increment('checkout.prepared');
            event(new ExtensionEvent('checkout.prepared', [
                'checkout_uuid' => $locked->uuid,
                'cart_uuid' => $cart->uuid,
                'total' => (int) $cart->total,
                'currency' => $cart->currency,
            ]));

            return ['approval_token' => $token];
        });

        return [
            'checkout' => $this->checkoutFromResponse($response),
            'approval_token' => (string) ($response['approval_token'] ?? ''),
        ];
    }

    public function approve(
        CheckoutSession $checkout,
        string $approvalToken,
        string $idempotencyKey,
        ?string $expectedPricingHash = null,
    ): CheckoutSession {
        $key = $this->requiredKey($idempotencyKey);
        $response = $this->operate($checkout, $key, 'checkout.approve', [
            'approval_token_hash' => hash('sha256', $approvalToken),
            'expected_pricing_hash' => $expectedPricingHash,
        ], function (CheckoutSession $locked) use ($approvalToken, $key, $expectedPricingHash): array {
            if ((string) $locked->status !== CheckoutStatus::READY) {
                throw ValidationException::withMessages(['checkout' => 'Only a ready checkout can be approved.']);
            }
            if ($locked->approval_expires_at?->isPast() ?? true) {
                throw ValidationException::withMessages(['approval_token' => 'The checkout approval has expired and must be prepared again.']);
            }
            if (! is_string($locked->approval_token_hash) || ! hash_equals($locked->approval_token_hash, hash('sha256', $approvalToken))) {
                throw ValidationException::withMessages(['approval_token' => 'The checkout approval token is invalid.']);
            }

            $cart = ChatbotCart::query()->whereKey($locked->cart_id)->lockForUpdate()->firstOrFail();
            $this->assertCartAvailable($cart, true);
            if ($locked->shipping_quote_id !== null) {
                $this->shipping->assertQuoteStillValid($locked, $cart);
            }
            $pricingMatches = hash_equals((string) $locked->pricing_snapshot_hash, (string) $cart->pricing_snapshot_hash);
            if ($expectedPricingHash !== null) {
                $pricingMatches = $pricingMatches && hash_equals((string) $cart->pricing_snapshot_hash, $expectedPricingHash);
            }
            $taxContextMatches = is_string($locked->tax_context_hash)
                && is_string($cart->tax_context_hash)
                && hash_equals($locked->tax_context_hash, $cart->tax_context_hash);
            if (! $pricingMatches || ! $taxContextMatches || (int) $locked->cart_version !== (int) $cart->version) {
                throw ValidationException::withMessages(['checkout' => 'The cart, pricing, or tax context changed after checkout preparation and must be prepared again.']);
            }
            $this->assertReservationCoverage($cart);

            $this->transition($locked, CheckoutStatus::PAYMENT_PENDING);
            $locked->forceFill([
                'payment_attempt_key' => $key,
                'approval_token_hash' => null,
                'approval_expires_at' => null,
                'approved_at' => now(),
                'payment_pending_at' => now(),
                'expires_at' => now()->addMinutes($this->paymentTtlMinutes()),
            ])->save();

            Metrics::increment('checkout.approved');
            event(new ExtensionEvent('checkout.approved', [
                'checkout_uuid' => $locked->uuid,
                'total' => (int) $locked->total,
                'currency' => $locked->currency,
            ]));

            return [];
        });

        return $this->checkoutFromResponse($response);
    }

    public function cancel(CheckoutSession $checkout, ?string $reason = null, ?string $idempotencyKey = null): CheckoutSession
    {
        $response = $this->operate($checkout, $idempotencyKey, 'checkout.cancel', ['reason' => $reason], function (CheckoutSession $locked) use ($reason): array {
            if (CheckoutStatus::isTerminal((string) $locked->status)) {
                return [];
            }

            $this->releaseReservations((int) $locked->cart_id, 'checkout_cancelled');
            $this->pricing->releaseCouponReservation((int) $locked->cart_id);
            $this->transition($locked, CheckoutStatus::CANCELLED);
            $locked->forceFill([
                'active_key' => null,
                'approval_token_hash' => null,
                'approval_expires_at' => null,
                'cancelled_at' => now(),
                'failure_code' => $reason ? 'cancelled:' . substr($reason, 0, 60) : 'cancelled',
            ])->save();

            Metrics::increment('checkout.cancelled');
            event(new ExtensionEvent('checkout.cancelled', ['checkout_uuid' => $locked->uuid, 'reason' => $reason]));

            return [];
        });

        return $this->checkoutFromResponse($response);
    }

    public function markCompleted(CheckoutSession $checkout, string $completionKey, string $orderReference): CheckoutSession
    {
        $completionKey = $this->requiredKey($completionKey);
        $existing = CheckoutSession::query()->where('completion_key', $completionKey)->first();
        if ($existing) {
            if ((int) $existing->id !== (int) $checkout->id) {
                throw ValidationException::withMessages(['completion_key' => 'The completion key is already associated with another checkout.']);
            }
            return $this->hydrate($existing);
        }

        return DB::transaction(function () use ($checkout, $completionKey, $orderReference): CheckoutSession {
            $locked = CheckoutSession::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === CheckoutStatus::COMPLETED) {
                if ($locked->completion_key === $completionKey) {
                    return $this->hydrate($locked);
                }
                throw ValidationException::withMessages(['checkout' => 'This checkout has already been completed.']);
            }
            if ((string) $locked->status !== CheckoutStatus::PAYMENT_PENDING) {
                throw ValidationException::withMessages(['checkout' => 'Checkout must be payment pending before completion.']);
            }

            $reservations = InventoryReservation::query()
                ->where('cart_id', $locked->cart_id)
                ->where('status', InventoryReservationState::ACTIVE)
                ->lockForUpdate()
                ->get();
            foreach ($reservations as $reservation) {
                $this->inventory->commit($reservation);
            }
            $cart = ChatbotCart::query()->findOrFail($locked->cart_id);
            $this->carts->markConverted($cart);

            $this->transition($locked, CheckoutStatus::COMPLETED);
            $locked->forceFill([
                'active_key' => null,
                'completion_key' => $completionKey,
                'order_reference' => trim($orderReference),
                'completed_at' => now(),
                'expires_at' => null,
            ])->save();

            Metrics::increment('checkout.completed');
            event(new ExtensionEvent('checkout.completed', [
                'checkout_uuid' => $locked->uuid,
                'order_reference' => $locked->order_reference,
            ]));

            return $this->hydrate($locked);
        });
    }

    public function expireDue(int $limit = 500): int
    {
        $ids = CheckoutSession::query()
            ->whereNotIn('status', [CheckoutStatus::COMPLETED, CheckoutStatus::FAILED, CheckoutStatus::CANCELLED, CheckoutStatus::EXPIRED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired): void {
                $checkout = CheckoutSession::query()->whereKey($id)->lockForUpdate()->first();
                if (! $checkout || CheckoutStatus::isTerminal((string) $checkout->status)) {
                    return;
                }
                $this->expireLocked($checkout, 'ttl_expired');
                $expired++;
            });
        }

        return $expired;
    }

    /** @return array<string, mixed> */
    private function operate(
        CheckoutSession $checkout,
        ?string $idempotencyKey,
        string $operation,
        array $payload,
        Closure $callback,
    ): array {
        $key = $idempotencyKey !== null && trim($idempotencyKey) !== '' ? trim($idempotencyKey) : null;
        $requestHash = hash('sha256', json_encode($this->canonicalise([$operation, $payload]), JSON_THROW_ON_ERROR));

        if ($key !== null) {
            $existing = CheckoutOperation::query()
                ->where('checkout_session_id', $checkout->id)
                ->where('idempotency_key', $key)
                ->first();
            if ($existing) {
                $this->assertOperationMatches($existing, $requestHash);
                return (array) $existing->response;
            }
        }

        try {
            return DB::transaction(function () use ($checkout, $key, $requestHash, $operation, $callback): array {
                $locked = CheckoutSession::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();
                if ($key !== null) {
                    $existing = CheckoutOperation::query()
                        ->where('checkout_session_id', $locked->id)
                        ->where('idempotency_key', $key)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        $this->assertOperationMatches($existing, $requestHash);
                        return (array) $existing->response;
                    }
                }

                $extra = (array) $callback($locked);
                $response = ['checkout_id' => (int) $locked->id] + $extra;
                if ($key !== null) {
                    CheckoutOperation::query()->create([
                        'checkout_session_id' => $locked->id,
                        'idempotency_key' => $key,
                        'request_hash' => $requestHash,
                        'operation' => $operation,
                        'response' => $response,
                    ]);
                }

                return $response;
            });
        } catch (QueryException $exception) {
            if ($key !== null) {
                $existing = CheckoutOperation::query()
                    ->where('checkout_session_id', $checkout->id)
                    ->where('idempotency_key', $key)
                    ->first();
                if ($existing) {
                    $this->assertOperationMatches($existing, $requestHash);
                    return (array) $existing->response;
                }
            }
            throw $exception;
        }
    }

    private function checkoutFromResponse(array $response): CheckoutSession
    {
        return $this->hydrate(CheckoutSession::query()->findOrFail((int) $response['checkout_id']));
    }

    private function assertOperationMatches(CheckoutOperation $operation, string $requestHash): void
    {
        if (! hash_equals((string) $operation->request_hash, $requestHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The idempotency key was already used for a different checkout request.',
            ]);
        }
    }

    private function assertCartAvailable(ChatbotCart $cart, bool $requireLines): void
    {
        if ((string) $cart->status !== CartStatus::ACTIVE) {
            throw ValidationException::withMessages(['cart' => 'Only an active cart can be checked out.']);
        }
        if ($cart->expires_at?->isPast() ?? false) {
            throw ValidationException::withMessages(['cart' => 'The cart has expired.']);
        }
        if ($requireLines && (int) $cart->line_count < 1) {
            throw ValidationException::withMessages(['cart' => 'The cart is empty.']);
        }
    }

    private function assertMutable(CheckoutSession $checkout): void
    {
        if (! CheckoutStatus::isMutable((string) $checkout->status)) {
            throw ValidationException::withMessages(['checkout' => 'This checkout can no longer be changed.']);
        }
        if ($checkout->expires_at?->isPast() ?? false) {
            throw ValidationException::withMessages(['checkout' => 'This checkout has expired.']);
        }
    }

    private function assertCustomerAndDeliveryComplete(CheckoutSession $checkout): void
    {
        if (($checkout->email === null || $checkout->email === '') && ($checkout->phone === null || $checkout->phone === '')) {
            throw ValidationException::withMessages(['customer' => 'Customer contact details are required.']);
        }
        if (! in_array((string) $checkout->delivery_method, ['delivery', 'pickup', 'digital'], true)) {
            throw ValidationException::withMessages(['delivery_method' => 'A delivery method is required.']);
        }
        if ($checkout->delivery_method === 'delivery' && ! CheckoutAddress::isComplete((array) $checkout->shipping_address)) {
            throw ValidationException::withMessages(['shipping_address' => 'A complete shipping address is required.']);
        }
    }

    /** @return list<array<string, mixed>> */
    private function reserveCartInventory(CheckoutSession $checkout, ChatbotCart $cart): array
    {
        $snapshot = [];
        $quantities = collect($cart->lines ?? [])
            ->groupBy(fn (array $line): int => (int) ($line['variant_id'] ?? 0))
            ->map(fn ($lines): int => (int) $lines->sum(fn (array $line): int => (int) ($line['quantity'] ?? 0)));

        $tracked = $this->inventoryTrackedVariantIds($quantities->keys()->map(static fn ($id): int => (int) $id)->all());
        foreach ($quantities as $variantId => $quantity) {
            if ((int) $variantId < 1 || $quantity < 1 || ! isset($tracked[(int) $variantId])) {
                continue;
            }
            $active = InventoryReservation::query()
                ->where('cart_id', $cart->id)
                ->where('variant_id', (int) $variantId)
                ->where('status', InventoryReservationState::ACTIVE)
                ->where('expires_at', '>', now())
                ->get();
            $reserved = (int) $active->sum('quantity');
            $missing = max(0, $quantity - $reserved);
            if ($missing > 0) {
                $active->push($this->inventory->reserve(
                    $cart,
                    (int) $variantId,
                    $missing,
                    'checkout:' . $checkout->uuid . ':variant:' . $variantId . ':quantity:' . $quantity,
                    null,
                    $this->approvalTtlMinutes(),
                    ['checkout_uuid' => $checkout->uuid],
                ));
            }

            foreach ($active as $reservation) {
                $snapshot[] = [
                    'reservation_uuid' => $reservation->uuid,
                    'variant_id' => (int) $reservation->variant_id,
                    'location_id' => $reservation->location_id ? (int) $reservation->location_id : null,
                    'quantity' => (int) $reservation->quantity,
                    'expires_at' => $reservation->expires_at?->toIso8601String(),
                ];
            }
        }

        $this->assertReservationCoverage($cart);
        return $snapshot;
    }

    private function assertReservationCoverage(ChatbotCart $cart): void
    {
        $needed = collect($cart->lines ?? [])
            ->groupBy(fn (array $line): int => (int) ($line['variant_id'] ?? 0))
            ->map(fn ($lines): int => (int) $lines->sum(fn (array $line): int => (int) ($line['quantity'] ?? 0)));
        $reserved = InventoryReservation::query()
            ->where('cart_id', $cart->id)
            ->where('status', InventoryReservationState::ACTIVE)
            ->where('expires_at', '>', now())
            ->get()
            ->groupBy('variant_id')
            ->map(fn ($rows): int => (int) $rows->sum('quantity'));

        $tracked = $this->inventoryTrackedVariantIds($needed->keys()->map(static fn ($id): int => (int) $id)->all());
        foreach ($needed as $variantId => $quantity) {
            if (! isset($tracked[(int) $variantId])) {
                continue;
            }
            if ((int) ($reserved[(int) $variantId] ?? 0) < $quantity) {
                throw ValidationException::withMessages([
                    'inventory' => sprintf('Inventory reservation coverage is incomplete for variant %d.', $variantId),
                ]);
            }
        }
    }

    /** @param list<int> $variantIds @return array<int, true> */
    private function inventoryTrackedVariantIds(array $variantIds): array
    {
        $variants = ProductVariant::query()->with('product')->whereIn('id', $variantIds)->get();
        $tracked = [];
        foreach ($variants as $variant) {
            $variantMetadata = (array) ($variant->metadata ?? []);
            $productMetadata = (array) ($variant->product?->metadata ?? []);
            $productType = strtolower((string) ($variant->product?->product_type ?? 'physical'));
            $isTracked = ($variantMetadata['track_inventory'] ?? $productMetadata['track_inventory'] ?? true) !== false
                && ! in_array($productType, ['digital', 'service'], true);
            if ($isTracked) {
                $tracked[(int) $variant->id] = true;
            }
        }

        return $tracked;
    }

    /** @return array{code: string, name: string, amount: int, metadata: array<string, mixed>, source: string} */
    private function resolveDeliveryOption(string $method, array $input): array
    {
        $code = trim((string) ($input['code'] ?? $method));
        $options = (array) config('chatbot-ecommerce.checkout.delivery_options', []);
        $configured = $options[$code] ?? null;
        if (is_array($configured)) {
            $configuredMethod = strtolower((string) ($configured['method'] ?? $method));
            if ($configuredMethod !== $method) {
                throw ValidationException::withMessages(['code' => 'The delivery option does not match the selected delivery method.']);
            }
            return [
                'code' => $code,
                'name' => (string) ($configured['name'] ?? ucfirst($method)),
                'amount' => max(0, (int) ($configured['amount'] ?? 0)),
                'metadata' => (array) ($configured['metadata'] ?? []),
                'source' => 'configuration',
            ];
        }

        if (in_array($method, ['pickup', 'digital'], true)) {
            return [
                'code' => $code,
                'name' => (string) ($input['name'] ?? ucfirst($method)),
                'amount' => 0,
                'metadata' => (array) ($input['metadata'] ?? []),
                'source' => 'built_in',
            ];
        }

        if ((bool) config('chatbot-ecommerce.checkout.allow_client_shipping_quotes', false)) {
            return [
                'code' => $code,
                'name' => (string) ($input['name'] ?? ucfirst($method)),
                'amount' => max(0, (int) ($input['amount'] ?? 0)),
                'metadata' => (array) ($input['metadata'] ?? []),
                'source' => 'client',
            ];
        }

        throw ValidationException::withMessages([
            'code' => 'The delivery option is not configured. Configure a server-side delivery option before checkout.',
        ]);
    }

    private function releaseReservations(int $cartId, string $reason): void
    {
        $reservations = InventoryReservation::query()
            ->where('cart_id', $cartId)
            ->where('status', InventoryReservationState::ACTIVE)
            ->get();
        foreach ($reservations as $reservation) {
            $this->inventory->release($reservation, $reason);
        }
    }

    private function expireLocked(CheckoutSession $checkout, string $reason): void
    {
        $this->releaseReservations((int) $checkout->cart_id, 'checkout_expired');
        $this->pricing->releaseCouponReservation((int) $checkout->cart_id);
        $this->transition($checkout, CheckoutStatus::EXPIRED);
        $checkout->forceFill([
            'active_key' => null,
            'approval_token_hash' => null,
            'approval_expires_at' => null,
            'failure_code' => $reason,
        ])->save();
        Metrics::increment('checkout.expired');
        event(new ExtensionEvent('checkout.expired', ['checkout_uuid' => $checkout->uuid, 'reason' => $reason]));
    }

    private function syncTaxContextToCart(CheckoutSession $checkout, ChatbotCart $cart): bool
    {
        $billing = CheckoutAddress::normalise((array) ($checkout->billing_address ?? []));
        $shipping = CheckoutAddress::normalise((array) ($checkout->shipping_address ?? []));
        $taxAddress = CheckoutAddress::isComplete($shipping) ? $shipping : $billing;
        $metadata = (array) ($cart->metadata ?? []);
        $before = [
            'billing_address' => (array) ($metadata['billing_address'] ?? []),
            'shipping_address' => (array) ($metadata['shipping_address'] ?? []),
            'tax_address' => (array) ($metadata['tax_address'] ?? []),
            'tax_exemption_key' => $metadata['tax_exemption_key'] ?? null,
        ];
        $after = [
            'billing_address' => $billing,
            'shipping_address' => $shipping,
            'tax_address' => $taxAddress,
            'tax_exemption_key' => $checkout->tax_exemption_key ?: null,
        ];
        $metadata = array_replace($metadata, $after);
        $cart->forceFill(['metadata' => $metadata])->save();

        return hash('sha256', json_encode($before, JSON_THROW_ON_ERROR))
            !== hash('sha256', json_encode($after, JSON_THROW_ON_ERROR));
    }

    private function syncCartSnapshot(CheckoutSession $checkout, ChatbotCart $cart): void
    {
        $checkout->forceFill([
            'currency' => $cart->currency,
            'cart_version' => (int) $cart->version,
            'pricing_snapshot_uuid' => $cart->pricing_snapshot_uuid,
            'pricing_snapshot_hash' => $cart->pricing_snapshot_hash,
            'pricing_snapshot' => $cart->price_snapshot ?? [],
            'subtotal' => (int) $cart->subtotal,
            'discount_total' => (int) $cart->discount_total,
            'tax_total' => (int) $cart->tax_total,
            'tax_zone_id' => $cart->tax_zone_id,
            'tax_context_hash' => $cart->tax_context_hash,
            'tax_breakdown' => $cart->tax_breakdown ?? [],
            'shipping_total' => (int) $cart->shipping_total,
            'total' => (int) $cart->total,
        ])->save();
    }

    private function transition(CheckoutSession $checkout, string $target): void
    {
        if (! CheckoutStatus::canTransition((string) $checkout->status, $target)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Checkout cannot transition from %s to %s.', $checkout->status, $target),
            ]);
        }
        $checkout->status = $target;
    }

    private function requiredKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid Idempotency-Key is required.']);
        }
        return $key;
    }

    private function canonicalise(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalise($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        return $value;
    }

    private function hydrate(CheckoutSession $checkout): CheckoutSession
    {
        return $checkout->refresh()->load(['cart', 'shippingQuote', 'paymentIntent']);
    }

    private function ttlMinutes(): int
    {
        return min(max((int) config('chatbot-ecommerce.checkout.ttl_minutes', 30), 5), 1440);
    }

    private function approvalTtlMinutes(): int
    {
        return min(max((int) config('chatbot-ecommerce.checkout.approval_ttl_minutes', 15), 1), 120);
    }

    private function paymentTtlMinutes(): int
    {
        return min(max((int) config('chatbot-ecommerce.checkout.payment_ttl_minutes', 30), 5), 1440);
    }
}
