<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;

interface MarketplaceReadProvider
{
    public function key(): string;
    /** @return array<int,string> */
    public function capabilities(): array;
    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function searchListings(MarketplaceConnection $connection, array $query): array;
    /** @return array<string,mixed>|null */
    public function getListing(MarketplaceConnection $connection, string $externalListingId): ?array;
    /** @return array<string,mixed> */
    public function getInventory(MarketplaceConnection $connection, array $externalListingIds): array;
    /** @param array<string,mixed> $cursor @return array<string,mixed> */
    public function importOrders(MarketplaceConnection $connection, array $cursor = []): array;
}
