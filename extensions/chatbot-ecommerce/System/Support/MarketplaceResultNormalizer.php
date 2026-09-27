<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MarketplaceResultNormalizer
{
    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public static function listing(string $provider, array $payload): array
    {
        $price = is_array($payload['price'] ?? null) ? $payload['price'] : [];
        $images = array_values(array_filter((array) ($payload['images'] ?? []), 'is_string'));
        $seller = is_array($payload['seller'] ?? null) ? $payload['seller'] : [];
        $safe = [
            'provider' => strtolower($provider),
            'external_listing_id' => trim((string) ($payload['external_listing_id'] ?? $payload['id'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'price_amount' => max(0, (int) ($price['amount'] ?? $payload['price_amount'] ?? 0)),
            'currency' => strtoupper((string) ($price['currency'] ?? $payload['currency'] ?? 'USD')),
            'image_url' => $images[0] ?? ($payload['image_url'] ?? null),
            'product_url' => $payload['url'] ?? $payload['product_url'] ?? null,
            'availability' => (string) ($payload['availability'] ?? 'unknown'),
            'seller_name' => trim((string) ($seller['name'] ?? $payload['seller_name'] ?? '')),
            'shipping_summary' => $payload['shipping_summary'] ?? null,
            'bnpl_summary' => $payload['bnpl_summary'] ?? null,
            'attributes' => is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [],
            'relevance_score' => max(0.0, min(1.0, (float) ($payload['relevance_score'] ?? 0.0))),
        ];
        $safe['source_hash'] = hash('sha256', json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $safe;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public static function order(string $provider, array $payload): array
    {
        $buyer = is_array($payload['buyer'] ?? null) ? $payload['buyer'] : [];
        $lines = [];
        foreach ((array) ($payload['lines'] ?? []) as $line) {
            if (! is_array($line)) { continue; }
            $lines[] = [
                'external_line_id' => (string) ($line['external_line_id'] ?? $line['id'] ?? ''),
                'sku' => $line['sku'] ?? null,
                'title' => (string) ($line['title'] ?? ''),
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'unit_amount' => max(0, (int) ($line['unit_amount'] ?? 0)),
                'tax_amount' => max(0, (int) ($line['tax_amount'] ?? 0)),
            ];
        }
        $safe = [
            'provider' => strtolower($provider),
            'external_order_id' => trim((string) ($payload['external_order_id'] ?? $payload['id'] ?? '')),
            'status' => (string) ($payload['status'] ?? 'unknown'),
            'currency' => strtoupper((string) ($payload['currency'] ?? 'USD')),
            'total_amount' => max(0, (int) ($payload['total_amount'] ?? $payload['total'] ?? 0)),
            'buyer' => ['display_name' => trim((string) ($buyer['display_name'] ?? ''))],
            'placed_at' => $payload['placed_at'] ?? null,
            'updated_at' => $payload['updated_at'] ?? null,
            'fulfillment_status' => $payload['fulfillment_status'] ?? null,
            'lines' => $lines,
        ];
        $safe['source_hash'] = hash('sha256', json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $safe;
    }
}
