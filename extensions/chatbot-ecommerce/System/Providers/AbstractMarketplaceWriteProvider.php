<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Providers;

use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceWriteProvider;
use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceWriteTransport;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceCapability;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceResultNormalizer;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteOperation;
use RuntimeException;

abstract class AbstractMarketplaceWriteProvider implements MarketplaceWriteProvider
{
    public function __construct(protected readonly MarketplaceWriteTransport $transport) {}

    /** @return array<string,string> */
    abstract protected function operations(): array;

    public function capabilities(): array
    {
        return MarketplaceCapability::writeOnly();
    }

    public function execute(
        MarketplaceConnection $connection,
        string $externalListingId,
        string $operation,
        array $changes,
        string $expectedSourceHash,
        string $idempotencyKey
    ): array {
        $operation = MarketplaceWriteOperation::capability($operation);
        $providerOperation = $this->operations()[$operation] ?? null;
        if ($providerOperation === null) {
            throw new RuntimeException("{$this->key()} does not support {$operation}.");
        }
        $response = $this->transport->request(
            $this->key(),
            $connection,
            $providerOperation,
            $externalListingId,
            ['changes' => $changes, 'mode' => 'execute'],
            $expectedSourceHash,
            $idempotencyKey
        );

        return $this->normalizeResponse($response, $externalListingId);
    }

    public function rollback(
        MarketplaceConnection $connection,
        string $externalListingId,
        string $operation,
        array $rollbackState,
        string $expectedSourceHash,
        string $idempotencyKey
    ): array {
        $operation = MarketplaceWriteOperation::capability($operation);
        $providerOperation = $this->operations()[$operation] ?? null;
        if ($providerOperation === null) {
            throw new RuntimeException("{$this->key()} does not support rollback for {$operation}.");
        }
        $response = $this->transport->request(
            $this->key(),
            $connection,
            $providerOperation,
            $externalListingId,
            ['changes' => $rollbackState, 'mode' => 'rollback'],
            $expectedSourceHash,
            $idempotencyKey
        );

        return $this->normalizeResponse($response, $externalListingId);
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    private function normalizeResponse(array $response, string $externalListingId): array
    {
        $listingPayload = $response['listing'] ?? $response['item'] ?? null;
        if (! is_array($listingPayload)) {
            throw new RuntimeException('Marketplace write response did not include the authoritative listing state.');
        }
        $listingPayload['external_listing_id'] ??= $externalListingId;
        $listing = MarketplaceResultNormalizer::listing($this->key(), $listingPayload);

        return [
            'listing' => $listing,
            'provider_request_id' => (string) ($response['provider_request_id'] ?? $response['request_id'] ?? ''),
            'rate_limit' => is_array($response['rate_limit'] ?? null) ? $response['rate_limit'] : [],
            'warnings' => is_array($response['warnings'] ?? null) ? $response['warnings'] : [],
        ];
    }
}
