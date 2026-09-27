<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceOrderSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\UnifiedCommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\UnifiedOrderSourceSnapshot;
use App\Extensions\ChatbotEcommerce\System\Support\UnifiedOrderSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UnifiedOrderProjectorRuntime
{
    public function projectNative(CommerceOrder $native): UnifiedCommerceOrder
    {
        $native->loadMissing(['items', 'events', 'returns.items', 'paymentIntent.refunds']);
        $snapshot = [
            'kind' => 'native_order',
            'uuid' => $native->uuid,
            'order_number' => $native->order_number,
            'status' => $native->status,
            'fulfillment_status' => $native->fulfillment_status,
            'currency' => $native->currency,
            'subtotal' => (int) $native->subtotal,
            'discount_total' => (int) $native->discount_total,
            'tax_total' => (int) $native->tax_total,
            'shipping_total' => (int) $native->shipping_total,
            'total' => (int) $native->total,
            'refunded_total' => (int) $native->refunded_total,
            'customer_snapshot' => $native->customer_snapshot ?? [],
            'shipping_address' => $native->shipping_address ?? [],
            'payment' => $native->paymentIntent?->toArray(),
            'items' => $native->items->map->toArray()->all(),
            'returns' => $native->returns->map->toArray()->all(),
            'events' => $native->events->map->toArray()->all(),
            'placed_at' => $native->placed_at?->toIso8601String(),
            'updated_at' => $native->updated_at?->toIso8601String(),
        ];

        return $this->persist(
            (int) $native->chatbot_id,
            'native',
            'native',
            (string) $native->uuid,
            $snapshot,
            [
                'native_order_id' => $native->id,
                'marketplace_order_snapshot_id' => null,
                'connection_id' => null,
                'external_authoritative' => false,
                'status' => (string) $native->status,
                'fulfillment_status' => (string) $native->fulfillment_status,
                'payment_status' => (string) ($native->paymentIntent?->status ?? 'unpaid'),
                'currency' => strtoupper((string) $native->currency),
                'gross_total' => (int) $native->total,
                'discount_total' => (int) $native->discount_total,
                'tax_total' => (int) $native->tax_total,
                'shipping_total' => (int) $native->shipping_total,
                'refund_total' => (int) $native->refunded_total,
                'customer_identity_id' => $native->customer_identity_id,
                'buyer_display_name' => (string) (($native->customer_snapshot['name'] ?? $native->customer_snapshot['email'] ?? '') ?: ''),
                'shipping_address' => $native->shipping_address ?? [],
                'placed_at' => $native->placed_at,
                'source_updated_at' => $native->updated_at,
                'metadata' => ['order_number' => $native->order_number, 'source' => 'native'],
            ]
        );
    }

    public function projectMarketplace(MarketplaceOrderSnapshot $external): UnifiedCommerceOrder
    {
        $external->loadMissing('lines');
        $snapshot = (array) $external->snapshot;
        $snapshot['lines'] = $external->lines->map->toArray()->all();
        $source = UnifiedOrderSource::normalize((string) $external->provider);
        $scope = 'connection:' . (string) $external->connection_id;

        return $this->persist(
            (int) $external->chatbot_id,
            $source,
            $scope,
            (string) $external->external_order_id,
            $snapshot,
            [
                'native_order_id' => null,
                'marketplace_order_snapshot_id' => $external->id,
                'connection_id' => $external->connection_id,
                'external_authoritative' => true,
                'status' => (string) $external->status,
                'fulfillment_status' => (string) ($external->fulfillment_status ?? 'unknown'),
                'payment_status' => (string) ($snapshot['payment_status'] ?? $snapshot['financial_status'] ?? 'unknown'),
                'currency' => strtoupper((string) $external->currency),
                'gross_total' => (int) $external->total_amount,
                'discount_total' => (int) ($snapshot['discount_total'] ?? 0),
                'tax_total' => (int) ($snapshot['tax_total'] ?? $this->lineTax($snapshot['lines'] ?? [])),
                'shipping_total' => (int) ($snapshot['shipping_total'] ?? 0),
                'refund_total' => (int) ($snapshot['refund_total'] ?? 0),
                'chargeback_total' => (int) ($snapshot['chargeback_total'] ?? 0),
                'buyer_display_name' => $external->buyer_display_name,
                'shipping_address' => (array) ($snapshot['shipping_address'] ?? $snapshot['delivery_address'] ?? []),
                'placed_at' => $external->placed_at,
                'source_updated_at' => $external->provider_updated_at,
                'metadata' => ['external_order_id' => $external->external_order_id, 'provider' => $source, 'connection_id' => $external->connection_id],
            ]
        );
    }

    /** @param array<string,mixed> $snapshot */
    public function projectExternal(Chatbot $chatbot, string $sourceType, string $sourceScope, string $sourceOrderId, array $snapshot, ?int $connectionId = null): UnifiedCommerceOrder
    {
        $source = UnifiedOrderSource::normalize($sourceType);
        if (! UnifiedOrderSource::isExternal($source)) {
            throw ValidationException::withMessages(['source_type' => 'External projection requires Shopify, WooCommerce, or another external provider source.']);
        }
        if (trim($sourceOrderId) === '') {
            throw ValidationException::withMessages(['source_order_id' => 'The external source order ID is required.']);
        }

        return $this->persist(
            (int) $chatbot->getAttribute('id'),
            $source,
            trim($sourceScope) !== '' ? trim($sourceScope) : $source,
            trim($sourceOrderId),
            $snapshot,
            [
                'native_order_id' => null,
                'marketplace_order_snapshot_id' => null,
                'connection_id' => $connectionId,
                'external_authoritative' => UnifiedOrderSource::externalAuthoritative($source),
                'status' => (string) ($snapshot['status'] ?? 'unknown'),
                'fulfillment_status' => (string) ($snapshot['fulfillment_status'] ?? 'unknown'),
                'payment_status' => (string) ($snapshot['payment_status'] ?? $snapshot['financial_status'] ?? 'unknown'),
                'currency' => strtoupper((string) ($snapshot['currency'] ?? 'USD')),
                'gross_total' => (int) ($snapshot['total_amount'] ?? $snapshot['total'] ?? 0),
                'discount_total' => (int) ($snapshot['discount_total'] ?? 0),
                'tax_total' => (int) ($snapshot['tax_total'] ?? 0),
                'shipping_total' => (int) ($snapshot['shipping_total'] ?? 0),
                'refund_total' => (int) ($snapshot['refund_total'] ?? 0),
                'chargeback_total' => (int) ($snapshot['chargeback_total'] ?? 0),
                'buyer_display_name' => (string) ($snapshot['buyer']['display_name'] ?? $snapshot['buyer_display_name'] ?? ''),
                'shipping_address' => (array) ($snapshot['shipping_address'] ?? $snapshot['delivery_address'] ?? []),
                'placed_at' => $snapshot['placed_at'] ?? null,
                'source_updated_at' => $snapshot['updated_at'] ?? null,
                'metadata' => ['source' => $source, 'source_scope' => $sourceScope],
            ]
        );
    }

    /** @return array<string,int> */
    public function syncChatbot(Chatbot $chatbot): array
    {
        $chatbotId = (int) $chatbot->getAttribute('id');
        $native = $external = 0;
        CommerceOrder::query()->where('chatbot_id', $chatbotId)->orderBy('id')->chunkById(100, function ($orders) use (&$native): void {
            foreach ($orders as $order) { $this->projectNative($order); $native++; }
        });
        MarketplaceOrderSnapshot::query()->where('chatbot_id', $chatbotId)->orderBy('id')->chunkById(100, function ($orders) use (&$external): void {
            foreach ($orders as $order) { $this->projectMarketplace($order); $external++; }
        });
        return ['native_projected' => $native, 'external_projected' => $external, 'total_projected' => $native + $external];
    }

    /** @param array<string,mixed> $snapshot @param array<string,mixed> $attributes */
    private function persist(int $chatbotId, string $sourceType, string $sourceScope, string $sourceOrderId, array $snapshot, array $attributes): UnifiedCommerceOrder
    {
        $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $sourceHash = hash('sha256', $encoded === false ? serialize($snapshot) : $encoded);

        return DB::transaction(function () use ($chatbotId, $sourceType, $sourceScope, $sourceOrderId, $snapshot, $sourceHash, $attributes): UnifiedCommerceOrder {
            $order = UnifiedCommerceOrder::query()->firstOrNew([
                'chatbot_id' => $chatbotId,
                'source_type' => $sourceType,
                'source_scope' => $sourceScope,
                'source_order_id' => $sourceOrderId,
            ]);
            $order->fill($attributes + [
                'source_snapshot' => $snapshot,
                'source_hash' => $sourceHash,
                'last_projected_at' => now(),
            ]);
            $order->save();

            if (! UnifiedOrderSourceSnapshot::query()->where('unified_order_id', $order->id)->where('source_hash', $sourceHash)->exists()) {
                UnifiedOrderSourceSnapshot::query()->create([
                    'unified_order_id' => $order->id,
                    'sequence' => (int) $order->sourceSnapshots()->max('sequence') + 1,
                    'source_hash' => $sourceHash,
                    'snapshot' => $snapshot,
                    'captured_at' => now(),
                ]);
            }

            return $order->fresh(['sourceSnapshots']);
        });
    }

    /** @param array<int,mixed> $lines */
    private function lineTax(array $lines): int
    {
        return array_sum(array_map(static fn ($line): int => is_array($line) ? (int) ($line['tax_amount'] ?? 0) : 0, $lines));
    }
}
