<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;

interface MarketplaceWriteTransport
{
    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function request(
        string $provider,
        MarketplaceConnection $connection,
        string $operation,
        string $externalListingId,
        array $payload,
        string $expectedSourceHash,
        string $idempotencyKey
    ): array;
}
