<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceTransport;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GatewayMarketplaceTransport implements MarketplaceTransport
{
    public function __construct(private readonly ProviderCircuitBreakerRuntime $circuits) {}

    public function request(string $provider, MarketplaceConnection $connection, string $operation, array $payload = []): array
    {
        $chatbotId = (int) $connection->chatbot_id;
        $this->circuits->assertAvailable($chatbotId, $provider, $operation);
        try {
            $config = (array) config("chatbot-ecommerce.marketplaces.providers.{$provider}", []);
            $url = rtrim((string) ($config['gateway_url'] ?? ''), '/');
            if ($url === '') throw new RuntimeException("Marketplace gateway is not configured for {$provider}.");
            $body = [
                'operation' => $operation,
                'credential_reference' => (string) $connection->credential_reference,
                'marketplace' => $connection->marketplace,
                'region' => $connection->region,
                'external_account_id' => $connection->external_account_id,
                'configuration' => (array) $connection->configuration,
                'payload' => $payload,
            ];
            $secret = (string) ($config['gateway_secret'] ?? '');
            $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $response = Http::acceptJson()->asJson()
                ->connectTimeout((int) ($config['connect_timeout_seconds'] ?? 5))
                ->timeout((int) ($config['timeout_seconds'] ?? 20))
                ->retry((int) ($config['retries'] ?? 2), (int) ($config['retry_delay_ms'] ?? 250), throw: false)
                ->withHeaders([
                    'X-Marketplace-Provider' => $provider,
                    'X-Marketplace-Operation' => $operation,
                    'X-Credential-Reference' => (string) $connection->credential_reference,
                    'X-Payload-Signature' => $secret === '' ? '' : hash_hmac('sha256', $encoded, $secret),
                ])->post($url . '/v1/marketplace/read', $body);
            if (! $response->successful()) throw new RuntimeException("Marketplace gateway request failed with status {$response->status()}.");
            $result = $response->json();
            if (! is_array($result)) throw new RuntimeException('Marketplace gateway returned an invalid response.');
            $this->circuits->success($chatbotId, $provider, $operation);
            return $result;
        } catch (Throwable $exception) {
            $this->circuits->failure($chatbotId, $provider, $operation, $exception);
            throw $exception;
        }
    }
}
