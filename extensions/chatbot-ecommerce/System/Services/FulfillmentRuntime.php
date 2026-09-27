<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCartLine;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\Fulfillment;
use App\Extensions\ChatbotEcommerce\System\Models\FulfillmentItem;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutStatus;
use App\Extensions\ChatbotEcommerce\System\Support\FulfillmentStatus;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FulfillmentRuntime
{
    public function create(CheckoutSession $checkout, array $items, string $idempotencyKey, array $attributes = []): Fulfillment
    {
        $key = $this->requiredKey($idempotencyKey);
        $requestHash = hash('sha256', json_encode($this->canonicalise([$items, $attributes]), JSON_THROW_ON_ERROR));
        if ($existing = Fulfillment::query()->where('idempotency_key', $key)->first()) {
            if ((int) $existing->checkout_session_id !== (int) $checkout->id) {
                throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key belongs to another checkout.']);
            }
            if (! hash_equals((string) (($existing->metadata ?? [])['request_hash'] ?? ''), $requestHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for a different fulfilment request.']);
            }
            return $existing->load('items');
        }

        try {
            return DB::transaction(function () use ($checkout, $items, $key, $attributes, $requestHash): Fulfillment {
                $locked = CheckoutSession::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();
                if ((string) $locked->status !== CheckoutStatus::COMPLETED) {
                    throw ValidationException::withMessages(['checkout' => 'Only a completed checkout can be fulfilled.']);
                }

                $cartLines = ChatbotCartLine::query()->where('cart_id', $locked->cart_id)->lockForUpdate()->get()->keyBy('uuid');
                if ($cartLines->isEmpty()) {
                    throw ValidationException::withMessages(['items' => 'The checkout has no cart lines to fulfil.']);
                }

                $normalised = $this->normaliseItems($items, $cartLines->all());
                $lineIds = collect($normalised)->pluck('cart_line_id')->all();
                $already = FulfillmentItem::query()
                    ->whereIn('cart_line_id', $lineIds)
                    ->whereHas('fulfillment', fn ($query) => $query->where('status', '<>', FulfillmentStatus::CANCELLED))
                    ->selectRaw('cart_line_id, SUM(quantity) as fulfilled_quantity')
                    ->groupBy('cart_line_id')
                    ->pluck('fulfilled_quantity', 'cart_line_id');

                foreach ($normalised as $item) {
                    $line = $cartLines->firstWhere('id', $item['cart_line_id']);
                    $remaining = (int) $line->quantity - (int) ($already[$line->id] ?? 0);
                    if ($item['quantity'] > $remaining) {
                        throw ValidationException::withMessages([
                            'items' => sprintf('Only %d unit(s) remain unfulfilled for cart line %s.', max(0, $remaining), $line->uuid),
                        ]);
                    }
                }

                $fulfillment = Fulfillment::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'chatbot_id' => $locked->chatbot_id,
                    'checkout_session_id' => $locked->id,
                    'cart_id' => $locked->cart_id,
                    'order_reference' => $locked->order_reference,
                    'status' => FulfillmentStatus::PENDING,
                    'method_type' => (string) ($attributes['method_type'] ?? $locked->delivery_method ?? 'delivery'),
                    'location_id' => $attributes['location_id'] ?? null,
                    'carrier' => $attributes['carrier'] ?? null,
                    'service' => $attributes['service'] ?? null,
                    'customer_note' => $attributes['customer_note'] ?? null,
                    'internal_note' => $attributes['internal_note'] ?? null,
                    'idempotency_key' => $key,
                    'metadata' => array_replace_recursive((array) ($attributes['metadata'] ?? []), ['request_hash' => $requestHash]),
                ]);

                foreach ($normalised as $item) {
                    $line = $cartLines->firstWhere('id', $item['cart_line_id']);
                    FulfillmentItem::query()->create([
                        'fulfillment_id' => $fulfillment->id,
                        'cart_line_id' => $line->id,
                        'product_id' => $line->product_id,
                        'variant_id' => $line->variant_id,
                        'sku' => $line->sku,
                        'name' => $line->name,
                        'quantity' => $item['quantity'],
                        'metadata' => $item['metadata'],
                    ]);
                }

                $this->syncCheckoutStatus($locked);
                Metrics::increment('fulfillment.created');
                event(new ExtensionEvent('fulfillment.created', [
                    'fulfillment_uuid' => $fulfillment->uuid,
                    'checkout_uuid' => $locked->uuid,
                ]));

                return $fulfillment->load('items');
            });
        } catch (QueryException $exception) {
            if ($existing = Fulfillment::query()->where('idempotency_key', $key)->first()) {
                if (! hash_equals((string) (($existing->metadata ?? [])['request_hash'] ?? ''), $requestHash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for a different fulfilment request.']);
                }
                return $existing->load('items');
            }
            throw $exception;
        }
    }

    public function markProcessing(Fulfillment $fulfillment): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::PROCESSING, ['processing_at' => now()]);
    }

    public function markShipped(Fulfillment $fulfillment, array $tracking = []): Fulfillment
    {
        $trackingNumber = trim((string) ($tracking['tracking_number'] ?? $fulfillment->tracking_number ?? ''));
        if ((string) $fulfillment->method_type === 'delivery' && $trackingNumber === '' && (bool) config('chatbot-ecommerce.fulfillment.require_tracking_for_delivery', false)) {
            throw ValidationException::withMessages(['tracking_number' => 'A tracking number is required for delivery fulfilments.']);
        }

        return $this->transition($fulfillment, FulfillmentStatus::SHIPPED, [
            'carrier' => isset($tracking['carrier']) ? trim((string) $tracking['carrier']) : $fulfillment->carrier,
            'service' => isset($tracking['service']) ? trim((string) $tracking['service']) : $fulfillment->service,
            'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
            'tracking_url' => isset($tracking['tracking_url']) ? trim((string) $tracking['tracking_url']) : $fulfillment->tracking_url,
            'shipped_at' => now(),
        ]);
    }

    public function markDelivered(Fulfillment $fulfillment): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::DELIVERED, ['delivered_at' => now()]);
    }

    public function cancel(Fulfillment $fulfillment, ?string $reason = null): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::CANCELLED, [
            'cancelled_at' => now(),
            'internal_note' => $reason ? trim($reason) : $fulfillment->internal_note,
        ]);
    }

    /** @return array<string, int|string> */
    public function summaryForCheckout(CheckoutSession $checkout): array
    {
        $total = (int) ChatbotCartLine::query()->where('cart_id', $checkout->cart_id)->sum('quantity');
        $fulfilled = (int) FulfillmentItem::query()
            ->whereHas('fulfillment', fn ($query) => $query
                ->where('checkout_session_id', $checkout->id)
                ->whereIn('status', [FulfillmentStatus::SHIPPED, FulfillmentStatus::DELIVERED]))
            ->sum('quantity');
        $delivered = (int) FulfillmentItem::query()
            ->whereHas('fulfillment', fn ($query) => $query
                ->where('checkout_session_id', $checkout->id)
                ->where('status', FulfillmentStatus::DELIVERED))
            ->sum('quantity');

        return [
            'status' => FulfillmentStatus::aggregate($total, $fulfilled, $delivered),
            'total_quantity' => $total,
            'fulfilled_quantity' => min($fulfilled, $total),
            'delivered_quantity' => min($delivered, $total),
        ];
    }

    private function transition(Fulfillment $fulfillment, string $target, array $attributes): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $target, $attributes): Fulfillment {
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === $target) {
                return $locked->load('items');
            }
            if (! FulfillmentStatus::canTransition((string) $locked->status, $target)) {
                throw ValidationException::withMessages([
                    'status' => sprintf('Fulfilment cannot transition from %s to %s.', $locked->status, $target),
                ]);
            }

            $locked->forceFill(['status' => $target] + $attributes)->save();
            $checkout = CheckoutSession::query()->whereKey($locked->checkout_session_id)->lockForUpdate()->firstOrFail();
            $this->syncCheckoutStatus($checkout);

            Metrics::increment('fulfillment.' . $target);
            event(new ExtensionEvent('fulfillment.' . $target, [
                'fulfillment_uuid' => $locked->uuid,
                'checkout_uuid' => $checkout->uuid,
            ]));

            return $locked->refresh()->load('items');
        });
    }

    private function syncCheckoutStatus(CheckoutSession $checkout): void
    {
        $summary = $this->summaryForCheckout($checkout);
        $checkout->forceFill(['fulfillment_status' => $summary['status']])->save();
    }

    /** @param array<string, ChatbotCartLine> $cartLines @return list<array{cart_line_id:int,quantity:int,metadata:array}> */
    private function normaliseItems(array $items, array $cartLines): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one fulfilment item is required.']);
        }
        $normalised = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(["items.{$index}" => 'Each fulfilment item must be an object.']);
            }
            $uuid = trim((string) ($item['cart_line_uuid'] ?? ''));
            $line = $cartLines[$uuid] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);
            if (! $line || $quantity < 1) {
                throw ValidationException::withMessages(["items.{$index}" => 'A valid cart line UUID and positive quantity are required.']);
            }
            $lineId = (int) $line->id;
            if (! isset($normalised[$lineId])) {
                $normalised[$lineId] = ['cart_line_id' => $lineId, 'quantity' => 0, 'metadata' => []];
            }
            $normalised[$lineId]['quantity'] += $quantity;
            $normalised[$lineId]['metadata'] = array_replace_recursive($normalised[$lineId]['metadata'], (array) ($item['metadata'] ?? []));
        }
        return array_values($normalised);
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

    private function requiredKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid Idempotency-Key is required.']);
        }
        return $key;
    }
}
