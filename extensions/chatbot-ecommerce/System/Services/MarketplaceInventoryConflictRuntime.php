<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\InventoryItem;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryLocationStock;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryMapping;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceListingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;

final class MarketplaceInventoryConflictRuntime
{
    public function availableForListing(MarketplaceListingSnapshot $listing): ?int
    {
        $mapping = MarketplaceInventoryMapping::query()
            ->where('connection_id', $listing->connection_id)
            ->where('external_listing_id', $listing->external_listing_id)
            ->where('active', true)
            ->first();

        if ($mapping !== null) {
            if ($mapping->location_id !== null) {
                $stock = InventoryLocationStock::query()
                    ->where('variant_id', $mapping->variant_id)
                    ->where('location_id', $mapping->location_id)
                    ->first();
                return $stock === null ? 0 : (int) $stock->available;
            }
            $locationRows = InventoryLocationStock::query()->where('variant_id', $mapping->variant_id)->get();
            if ($locationRows->isNotEmpty()) {
                return (int) $locationRows->sum(static fn (InventoryLocationStock $stock): int => (int) $stock->available);
            }
            $inventory = InventoryItem::query()->where('variant_id', $mapping->variant_id)->first();
            return $inventory === null ? 0 : (int) $inventory->available;
        }

        $snapshot = (array) $listing->snapshot;
        $attributes = is_array($snapshot['attributes'] ?? null) ? $snapshot['attributes'] : [];
        $sku = trim((string) ($attributes['sku'] ?? $snapshot['sku'] ?? ''));
        if ($sku === '') return null;
        $variant = ProductVariant::query()->where('sku', $sku)->first();
        if ($variant === null) return null;
        $locationRows = InventoryLocationStock::query()->where('variant_id', $variant->id)->get();
        if ($locationRows->isNotEmpty()) return (int) $locationRows->sum(static fn (InventoryLocationStock $stock): int => (int) $stock->available);
        $inventory = InventoryItem::query()->where('variant_id', $variant->id)->first();
        return $inventory === null ? null : (int) $inventory->available;
    }
}
