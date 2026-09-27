<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceWriteTransport;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GatewayMarketplaceWriteTransport implements MarketplaceWriteTransport
{
    public function __construct(private readonly ProviderCircuitBreakerRuntime $circuits) {}

    public function request(string $provider, MarketplaceConnection $connection, string $operation, string $externalListingId, array $payload, string $expectedSourceHash, string $idempotencyKey): array
    {
        $chatbotId = (int) $connection->chatbot_id;
        $circuitOperation = 'write:' . $operation;
        $this->circuits->assertAvailable($chatbotId, $provider, $circuitOperation);
        try {
            $config = (array) config("chatbot-ecommerce.marketplaces.providers.{$provider}", []);
            $url = rtrim((string) ($config['gateway_url'] ?? ''), '/');
            $secret = (string) ($config['gateway_secret'] ?? '');
            if ($url === '' || $secret === '') throw new RuntimeException("Marketplace write gateway or signing secret is not configured for {$provider}.");
            $body = [
                'operation' => $operation, 'external_listing_id' => $externalListingId,
                'expected_source_hash' => $expectedSourceHash, 'idempotency_key' => $idempotencyKey,
                'credential_reference' => (string) $connection->credential_reference,
                'marketplace' => $connection->marketplace, 'region' => $connection->region,
                'external_account_id' => $connection->external_account_id,
                'configuration' => (array) $connection->configuration, 'payload' => $payload,
            ];
            $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $response = Http::acceptJson()->asJson()
                ->connectTimeout((int) ($config['connect_timeout_seconds'] ?? 5))
                ->timeout((int) ($config['write_timeout_seconds'] ?? $config['timeout_seconds'] ?? 30))
                ->retry((int) ($config['write_retries'] ?? 1), (int) ($config['retry_delay_ms'] ?? 250), throw: false)
                ->withHeaders([
                    'X-Marketplace-Provider' => $provider,
                    'X-Marketplace-Operation' => $operation,
                    'X-Credential-Reference' => (string) $connection->credential_reference,
                    'X-Idempotency-Key' => $idempotencyKey,
                    'X-Expected-Source-Hash' => $expectedSourceHash,
                    'X-Payload-Signature' => hash_hmac('sha256', $encoded, $secret),
                ])->post($url . '/v1/marketplace/write', $body);
            if (! $response->successful()) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 0);
                throw new RuntimeException("Marketplace write gateway failed with status {$response->status()} and retry_after {$retryAfter}.");
            }
            $result = $response->json();
            if (! is_array($result)) throw new RuntimeException('Marketplace write gateway returned an invalid response.');
            $this->circuits->success($chatbotId, $provider, $circuitOperation);
            return $result;
        } catch (Throwable $exception) {
            $this->circuits->failure($chatbotId, $provider, $circuitOperation, $exception);
            throw $exception;
        }
    }
}
