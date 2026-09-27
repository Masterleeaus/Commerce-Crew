<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Providers;

use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceReadProvider;
use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceTransport;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceCapability;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceResultNormalizer;

abstract class AbstractMarketplaceReadProvider implements MarketplaceReadProvider
{
    public function __construct(protected readonly MarketplaceTransport $transport) {}
    abstract protected function operations(): array;
    public function capabilities(): array { return MarketplaceCapability::readOnly(); }

    public function searchListings(MarketplaceConnection $connection, array $query): array
    {
        $response = $this->transport->request($this->key(), $connection, $this->operations()['search'], $query);
        $items = [];
        foreach ((array) ($response['items'] ?? $response['listings'] ?? []) as $item) {
            if (is_array($item)) { $items[] = MarketplaceResultNormalizer::listing($this->key(), $item); }
        }
        return ['items' => $items, 'next_cursor' => $response['next_cursor'] ?? null, 'total' => (int) ($response['total'] ?? count($items))];
    }

    public function getListing(MarketplaceConnection $connection, string $externalListingId): ?array
    {
        $response = $this->transport->request($this->key(), $connection, $this->operations()['get'], ['external_listing_id' => $externalListingId]);
        $item = $response['item'] ?? $response['listing'] ?? null;
        return is_array($item) ? MarketplaceResultNormalizer::listing($this->key(), $item) : null;
    }

    public function getInventory(MarketplaceConnection $connection, array $externalListingIds): array
    {
        return $this->transport->request($this->key(), $connection, $this->operations()['inventory'], ['external_listing_ids' => array_values($externalListingIds)]);
    }

    public function importOrders(MarketplaceConnection $connection, array $cursor = []): array
    {
        $response = $this->transport->request($this->key(), $connection, $this->operations()['orders'], ['cursor' => $cursor]);
        $orders = [];
        foreach ((array) ($response['orders'] ?? []) as $order) {
            if (is_array($order)) { $orders[] = MarketplaceResultNormalizer::order($this->key(), $order); }
        }
        return ['orders' => $orders, 'next_cursor' => (array) ($response['next_cursor'] ?? [])];
    }
}
