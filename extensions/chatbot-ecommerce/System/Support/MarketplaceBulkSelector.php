<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MarketplaceBulkSelector
{
    /** @param array<int,array<string,mixed>> $listings
     *  @param array<string,mixed> $filters
     *  @return array<int,array<string,mixed>>
     */
    public static function filter(array $listings, array $filters): array
    {
        $multi = static fn (mixed $value): array => array_values(array_filter(array_map(
            static fn ($item): string => strtolower(trim((string) $item)),
            is_array($value) ? $value : ($value === null ? [] : [$value])
        ), static fn (string $value): bool => $value !== ''));
        $providers = $multi($filters['provider'] ?? []);
        $statuses = $multi($filters['status'] ?? []);
        $brands = $multi($filters['brand'] ?? []);
        $categories = $multi($filters['category'] ?? []);
        $ids = $multi($filters['external_listing_ids'] ?? []);
        $minimum = isset($filters['minimum_price_minor']) ? (int) $filters['minimum_price_minor'] : null;
        $maximum = isset($filters['maximum_price_minor']) ? (int) $filters['maximum_price_minor'] : null;

        return array_values(array_filter($listings, static function (array $listing) use ($providers, $statuses, $brands, $categories, $ids, $minimum, $maximum): bool {
            $matches = static fn (array $allowed, mixed $actual): bool => $allowed === [] || in_array(strtolower(trim((string) $actual)), $allowed, true);
            $price = (int) ($listing['price_minor'] ?? 0);
            return $matches($providers, $listing['provider'] ?? '')
                && $matches($statuses, $listing['status'] ?? '')
                && $matches($brands, $listing['brand'] ?? '')
                && $matches($categories, $listing['category'] ?? '')
                && $matches($ids, $listing['external_listing_id'] ?? '')
                && ($minimum === null || $price >= $minimum)
                && ($maximum === null || $price <= $maximum);
        }));
    }
}
