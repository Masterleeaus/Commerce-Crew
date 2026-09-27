<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSearch;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceListingSnapshot;

final class MarketplaceCardRuntime
{
    /** @return array<string,mixed> */
    public function search(MarketplaceSearch $search): array
    {
        $results = $search->relationLoaded('results') ? $search->results : $search->results()->get();
        $cards = [];
        $fallback = [];
        foreach ($results as $index => $result) {
            $number = $index + 1;
            $cards[] = [
                'number' => $number,
                'provider' => $result->provider,
                'external_listing_id' => $result->external_listing_id,
                'title' => $result->title,
                'price_amount' => (int) $result->price_amount,
                'currency' => $result->currency,
                'availability' => $result->availability,
                'image_url' => $result->image_url,
                'product_url' => $result->product_url,
                'shipping_summary' => $result->shipping_summary,
                'bnpl_summary' => $result->bnpl_summary,
            ];
            $price = number_format(((int) $result->price_amount) / 100, 2);
            $link = $result->product_url ? " {$result->product_url}" : '';
            $fallback[] = "{$number}. {$result->title} — {$result->currency} {$price} ({$result->provider}){$link}";
        }
        $header = match ($search->status) {
            'queued' => 'Marketplace search queued.',
            'searching' => 'Searching marketplaces…',
            'partial' => 'Marketplace results are still arriving.',
            'failed' => 'Marketplace search could not be completed.',
            default => sprintf('Found %d marketplace result%s.', count($cards), count($cards) === 1 ? '' : 's'),
        };
        return [
            'schema' => ['type' => 'commerce.marketplace_search', 'version' => 1, 'search_uuid' => $search->uuid, 'status' => $search->status, 'progress' => ['completed' => (int) $search->completed_provider_count, 'total' => (int) $search->provider_count], 'items' => $cards],
            'fallback_text' => trim($header . "\n" . implode("\n", $fallback) . (count($fallback) ? "\nReply with a number to inspect that listing." : '')),
        ];
    }

    /** @return array<string,mixed> */
    public function listing(MarketplaceListingSnapshot $snapshot): array
    {
        $item = (array) $snapshot->snapshot;
        return [
            'schema' => ['type' => 'commerce.marketplace_listing', 'version' => 1, 'listing' => $item],
            'fallback_text' => trim(sprintf('%s — %s %.2f%s', (string) ($item['title'] ?? 'Marketplace listing'), (string) ($item['currency'] ?? 'USD'), ((int) ($item['price_amount'] ?? 0)) / 100, isset($item['product_url']) ? ' ' . $item['product_url'] : '')),
        ];
    }
}
