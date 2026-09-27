<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrderEvent;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrderItem;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceReturn;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceReturnItem;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutStatus;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use App\Extensions\ChatbotEcommerce\System\Support\OrderLifecycle;
use App\Extensions\ChatbotEcommerce\System\Support\ReturnLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class NativeOrderRuntime
{
    public function __construct(private readonly UnifiedOrderProjectorRuntime $projector) {}

    public function createFromCheckout(CheckoutSession $checkout): CommerceOrder
    {
        if ($existing = CommerceOrder::query()->where('checkout_session_id', $checkout->id)->first()) {
            $existing = $this->hydrate($existing);
            $this->projector->projectNative($existing);
            return $existing;
        }

        $order = DB::transaction(function () use ($checkout): CommerceOrder {
            $locked = CheckoutSession::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== CheckoutStatus::COMPLETED) {
                throw ValidationException::withMessages(['checkout' => 'Only a completed checkout can become an order.']);
            }
            if ($existing = CommerceOrder::query()->where('checkout_session_id', $locked->id)->lockForUpdate()->first()) {
                return $this->hydrate($existing);
            }

            $cart = ChatbotCart::query()->with('cartLines')->whereKey($locked->cart_id)->lockForUpdate()->firstOrFail();
            $payment = $locked->paymentIntent()->first();
            $order = CommerceOrder::query()->create([
                'order_number' => $this->orderNumber(),
                'chatbot_id' => $locked->chatbot_id,
                'cart_id' => $cart->id,
                'checkout_session_id' => $locked->id,
                'payment_intent_id' => $payment?->id,
                'customer_identity_id' => $locked->customer_identity_id,
                'conversation_id' => $locked->conversation_id,
                'session_id' => $locked->session_id,
                'source' => 'internal',
                'status' => 'confirmed',
                'fulfillment_status' => $locked->fulfillment_status ?: 'unfulfilled',
                'currency' => $locked->currency,
                'subtotal' => (int) $locked->subtotal,
                'discount_total' => (int) $locked->discount_total,
                'tax_total' => (int) $locked->tax_total,
                'shipping_total' => (int) $locked->shipping_total,
                'total' => (int) $locked->total,
                'customer_snapshot' => ['email' => $locked->email, 'phone' => $locked->phone, 'note' => $locked->customer_note],
                'billing_address' => $locked->billing_address ?? [],
                'shipping_address' => $locked->shipping_address ?? [],
                'pricing_snapshot' => $locked->pricing_snapshot ?? [],
                'tax_breakdown' => $locked->tax_breakdown ?? [],
                'payment_snapshot' => $payment ? ['uuid' => $payment->uuid, 'provider' => $payment->provider, 'method' => $payment->method, 'status' => $payment->status, 'captured_amount' => (int) $payment->captured_amount] : [],
                'placed_at' => now(),
                'confirmed_at' => now(),
                'metadata' => ['checkout_uuid' => $locked->uuid, 'cart_uuid' => $cart->uuid],
            ]);

            foreach ($cart->cartLines as $line) {
                CommerceOrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line->product_id,
                    'variant_id' => $line->variant_id,
                    'sku' => $line->sku,
                    'name' => $line->name,
                    'variant_name' => $line->variant_name,
                    'quantity' => (int) $line->quantity,
                    'list_unit_price' => (int) ($line->list_unit_price ?? $line->unit_price),
                    'unit_price' => (int) $line->unit_price,
                    'discount_total' => (int) $line->discount_total,
                    'tax_total' => (int) $line->tax_total,
                    'line_total' => (int) $line->line_total,
                    'product_snapshot' => ['price_snapshot' => $line->price_snapshot ?? [], 'customisation' => $line->customisation ?? []],
                    'metadata' => $line->metadata ?? [],
                ]);
            }

            $this->event($order, 'order.created', null, $this->summary($order));
            event(new ExtensionEvent('commerce.order.created', ['order_uuid' => $order->uuid, 'order_number' => $order->order_number]));
            Metrics::increment('commerce.order.created');

            return $this->hydrate($order);
        });
        $this->projector->projectNative($order);
        return $order;
    }

    public function transition(CommerceOrder $order, string $status, array $metadata = []): CommerceOrder
    {
        $updated = DB::transaction(function () use ($order, $status, $metadata): CommerceOrder {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === $status) {
                return $this->hydrate($locked);
            }
            if (! OrderLifecycle::canTransition((string) $locked->status, $status)) {
                throw ValidationException::withMessages(['status' => "Order cannot move from {$locked->status} to {$status}."]);
            }
            $before = $this->summary($locked);
            $values = ['status' => $status];
            if ($status === 'completed') { $values['completed_at'] = now(); }
            if ($status === 'cancelled') { $values['cancelled_at'] = now(); }
            $locked->forceFill($values)->save();
            $this->event($locked, 'order.status_changed', $before, $this->summary($locked), $metadata);

            return $this->hydrate($locked);
        });
        $this->projector->projectNative($updated);
        return $updated;
    }

    /** @param array<int,array<string,mixed>> $items */
    public function requestReturn(CommerceOrder $order, array $items, string $resolution, ?string $reason = null, ?int $customerIdentityId = null): CommerceReturn
    {
        $grouped = [];
        foreach ($items as $requested) {
            $uuid = trim((string) ($requested['order_item_uuid'] ?? ''));
            if ($uuid === '') {
                continue;
            }
            if (! isset($grouped[$uuid])) {
                $grouped[$uuid] = $requested;
                $grouped[$uuid]['quantity'] = 0;
            }
            $grouped[$uuid]['quantity'] += (int) ($requested['quantity'] ?? 0);
        }
        $items = array_values($grouped);

        if (! in_array((string) $order->status, ['fulfilled', 'completed', 'partially_refunded'], true)) {
            throw ValidationException::withMessages(['order' => 'Returns can only be requested after fulfilment.']);
        }
        if (! in_array($resolution, ['refund', 'exchange', 'store_credit'], true)) {
            throw ValidationException::withMessages(['requested_resolution' => 'Unsupported return resolution.']);
        }

        return DB::transaction(function () use ($order, $items, $resolution, $reason, $customerIdentityId): CommerceReturn {
            $lockedOrder = CommerceOrder::query()->with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $requestedAmount = 0;
            $validated = [];
            foreach ($items as $requested) {
                $item = CommerceOrderItem::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('uuid', (string) ($requested['order_item_uuid'] ?? ''))
                    ->lockForUpdate()
                    ->first();
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'A requested order item was not found.']);
                }
                $quantity = (int) ($requested['quantity'] ?? 0);
                $available = (int) $item->quantity - (int) $item->returned_quantity;
                if ($quantity <= 0 || $quantity > $available) {
                    throw ValidationException::withMessages(['items' => 'Return quantity exceeds the remaining returnable quantity.']);
                }
                $amount = (int) round(((int) $item->line_total / max(1, (int) $item->quantity)) * $quantity);
                $requestedAmount += $amount;
                $validated[] = [$item, $quantity, $amount, $requested];
            }
            if ($validated === []) {
                throw ValidationException::withMessages(['items' => 'At least one return item is required.']);
            }

            $return = CommerceReturn::query()->create([
                'rma_number' => $this->rmaNumber(),
                'order_id' => $lockedOrder->id,
                'customer_identity_id' => $customerIdentityId ?? $lockedOrder->customer_identity_id,
                'status' => 'requested',
                'reason' => $reason,
                'requested_resolution' => $resolution,
                'requested_amount' => $requestedAmount,
                'currency' => $lockedOrder->currency,
            ]);
            foreach ($validated as [$item, $quantity, $amount, $requested]) {
                CommerceReturnItem::query()->create([
                    'return_id' => $return->id,
                    'order_item_id' => $item->id,
                    'quantity' => $quantity,
                    'reason_code' => $requested['reason_code'] ?? null,
                    'condition' => $requested['condition'] ?? null,
                    'amount' => $amount,
                    'metadata' => $requested['metadata'] ?? [],
                ]);
                $item->forceFill(['returned_quantity' => (int) $item->returned_quantity + $quantity])->save();
            }
            $this->event($lockedOrder, 'return.requested', null, ['return_uuid' => $return->uuid, 'rma_number' => $return->rma_number, 'requested_amount' => $requestedAmount]);
            event(new ExtensionEvent('commerce.return.requested', ['return_uuid' => $return->uuid, 'order_uuid' => $lockedOrder->uuid]));
            Metrics::increment('commerce.return.requested');

            return $return->load('items.orderItem');
        });
    }

    public function transitionReturn(CommerceReturn $return, string $status, array $metadata = []): CommerceReturn
    {
        return DB::transaction(function () use ($return, $status, $metadata): CommerceReturn {
            $locked = CommerceReturn::query()->with('items')->whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($status === 'refunded') {
                throw ValidationException::withMessages(['status' => 'Use the refund endpoint so payment and order records remain consistent.']);
            }
            if (! ReturnLifecycle::canTransition((string) $locked->status, $status)) {
                throw ValidationException::withMessages(['status' => "Return cannot move from {$locked->status} to {$status}."]);
            }
            $mergedMetadata = array_replace_recursive((array) $locked->metadata, $metadata);
            if (in_array($status, ['rejected', 'cancelled'], true) && ! ($mergedMetadata['quantities_released'] ?? false)) {
                foreach ($locked->items as $returnItem) {
                    $orderItem = CommerceOrderItem::query()->whereKey($returnItem->order_item_id)->lockForUpdate()->firstOrFail();
                    $orderItem->forceFill(['returned_quantity' => max(0, (int) $orderItem->returned_quantity - (int) $returnItem->quantity)])->save();
                }
                $mergedMetadata['quantities_released'] = true;
            }
            $values = ['status' => $status, 'metadata' => $mergedMetadata];
            if ($status === 'approved') { $values['approved_at'] = now(); }
            if ($status === 'received') { $values['received_at'] = now(); }
            if (in_array($status, ['exchanged', 'rejected'], true)) { $values['resolved_at'] = now(); }
            if ($status === 'cancelled') { $values['cancelled_at'] = now(); }
            $locked->forceFill($values)->save();
            $this->event($locked->order()->firstOrFail(), 'return.status_changed', null, ['return_uuid' => $locked->uuid, 'status' => $status]);

            return $locked->load('items.orderItem', 'order');
        });
    }

    public function recordRefund(CommerceReturn $return, int $amount, array $paymentMetadata = []): CommerceReturn
    {
        return DB::transaction(function () use ($return, $amount, $paymentMetadata): CommerceReturn {
            $locked = CommerceReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            if (! in_array((string) $locked->status, ['approved', 'received', 'inspected'], true)) {
                throw ValidationException::withMessages(['return' => 'The return must be approved or received before refunding.']);
            }
            if ($amount <= 0 || $amount > ((int) $locked->requested_amount - (int) $locked->refunded_amount)) {
                throw ValidationException::withMessages(['amount' => 'Refund amount exceeds the outstanding requested return amount.']);
            }
            $order = CommerceOrder::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $newReturnRefunded = (int) $locked->refunded_amount + $amount;
            $newOrderRefunded = (int) $order->refunded_total + $amount;
            $returnComplete = $newReturnRefunded >= (int) $locked->requested_amount;
            $orderStatus = $newOrderRefunded >= (int) $order->total ? 'refunded' : 'partially_refunded';
            if ((string) $order->status !== $orderStatus && ! OrderLifecycle::canTransition((string) $order->status, $orderStatus)) {
                throw ValidationException::withMessages(['order' => 'The order cannot enter the required refund state.']);
            }
            $returnMetadata = (array) $locked->metadata;
            $refundHistory = array_values((array) ($returnMetadata['payment_refunds'] ?? []));
            $refundHistory[] = $paymentMetadata;
            $returnMetadata['payment_refunds'] = $refundHistory;
            $locked->forceFill([
                'refunded_amount' => $newReturnRefunded,
                'status' => $returnComplete ? 'refunded' : $locked->status,
                'resolved_at' => $returnComplete ? now() : $locked->resolved_at,
                'metadata' => $returnMetadata,
            ])->save();
            $before = $this->summary($order);
            $order->forceFill(['refunded_total' => $newOrderRefunded, 'status' => $orderStatus])->save();
            $this->event($order, 'return.refunded', $before, $this->summary($order), ['return_uuid' => $locked->uuid, 'amount' => $amount] + $paymentMetadata);

            return $locked->load('items.orderItem', 'order');
        });
    }

    private function event(CommerceOrder $order, string $type, ?array $before, array $after, array $metadata = []): void
    {
        CommerceOrderEvent::query()->create(['order_id' => $order->id, 'event_type' => $type, 'before_state' => $before, 'after_state' => $after, 'metadata' => $metadata]);
    }

    /** @return array<string,mixed> */
    private function summary(CommerceOrder $order): array
    {
        return ['status' => $order->status, 'fulfillment_status' => $order->fulfillment_status, 'total' => (int) $order->total, 'currency' => $order->currency];
    }

    private function hydrate(CommerceOrder $order): CommerceOrder
    {
        return $order->load('items', 'events', 'returns.items');
    }

    private function orderNumber(): string { return 'EC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)); }
    private function rmaNumber(): string { return 'RMA-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)); }
}
