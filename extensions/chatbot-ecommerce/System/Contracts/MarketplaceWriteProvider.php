<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;

interface MarketplaceWriteProvider
{
    public function key(): string;

    /** @return array<int,string> */
    public function capabilities(): array;

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    public function execute(
        MarketplaceConnection $connection,
        string $externalListingId,
        string $operation,
        array $changes,
        string $expectedSourceHash,
        string $idempotencyKey
    ): array;

    /** @param array<string,mixed> $rollbackState @return array<string,mixed> */
    public function rollback(
        MarketplaceConnection $connection,
        string $externalListingId,
        string $operation,
        array $rollbackState,
        string $expectedSourceHash,
        string $idempotencyKey
    ): array;
}
