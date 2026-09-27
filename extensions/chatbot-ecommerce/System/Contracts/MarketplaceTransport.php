<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;

interface MarketplaceTransport
{
    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function request(string $provider, MarketplaceConnection $connection, string $operation, array $payload = []): array;
}
