<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MarketplaceListingComposer
{
    /** @param array<string,mixed> $product @param array<string,mixed> $brandGuide @return array<string,mixed> */
    public static function compose(string $provider, array $product, array $brandGuide = []): array
    {
        $provider = strtolower(trim($provider));
        $name = trim((string) ($product['name'] ?? 'Product'));
        $brand = trim((string) ($product['brand'] ?? ''));
        $summary = trim((string) ($product['summary'] ?? ''));
        $features = array_values(array_filter(array_map('strval', (array) ($product['features'] ?? []))));
        $attributes = (array) ($product['attributes'] ?? []);
        $prefix = $brand !== '' && ! str_starts_with(strtolower($name), strtolower($brand)) ? $brand.' '.$name : $name;
        $description = self::clean(trim($summary."\n\n".implode("\n", array_map(static fn (string $f): string => '• '.$f, $features))), $brandGuide);
        return match ($provider) {
            'amazon' => [
                'title' => self::cut(self::clean($prefix, $brandGuide), 200),
                'bullet_points' => array_map(static fn (string $f): string => self::cut(self::clean($f, $brandGuide), 250), array_slice($features, 0, 5)),
                'description' => self::cut($description, 2000),
                'attributes' => $attributes,
            ],
            'ebay' => [
                'title' => self::cut(self::clean($prefix, $brandGuide), 80),
                'description' => self::cut($description, 4000),
                'item_specifics' => $attributes,
            ],
            'etsy' => [
                'title' => self::cut(self::clean($prefix, $brandGuide), 140),
                'description' => self::cut($description, 5000),
                'tags' => array_slice(array_values(array_unique(array_filter(array_map(static fn ($v): string => self::cut(strtolower(trim((string) $v)), 20), array_merge($features, array_keys($attributes)))))), 0, 13),
                'attributes' => $attributes,
            ],
            default => [
                'title' => self::cut(self::clean($prefix, $brandGuide), 200),
                'description' => self::cut($description, 5000),
                'features' => array_slice($features, 0, 20),
                'attributes' => $attributes,
            ],
        };
    }

    /** @param array<string,mixed> $guide */
    private static function clean(string $value, array $guide): string
    {
        foreach ((array) ($guide['forbidden_terms'] ?? []) as $term) {
            $term = trim((string) $term);
            if ($term !== '') $value = str_ireplace($term, '', $value);
        }
        return trim((string) preg_replace('/\s{2,}/u', ' ', $value));
    }
    private static function cut(string $value, int $limit): string { return strlen($value) <= $limit ? $value : rtrim(substr($value, 0, $limit - 1)).'…'; }
}
