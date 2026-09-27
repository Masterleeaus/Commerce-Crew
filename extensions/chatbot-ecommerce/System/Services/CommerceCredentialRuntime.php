<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCredential;
use App\Extensions\ChatbotEcommerce\System\Support\CredentialRedactor;
use App\Extensions\ChatbotEcommerce\System\Tools\ShopifyToolHandler;
use App\Extensions\ChatbotEcommerce\System\Tools\WooCommerceToolHandler;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CommerceCredentialRuntime
{
    /** @param array<string,mixed> $credentials @param array<string,mixed> $configuration */
    public function store(Chatbot $chatbot, string $provider, array $credentials, array $configuration = [], ?string $credentialReference = null, ?int $actorUserId = null): CommerceCredential
    {
        $provider = $this->provider($provider);
        $this->assertOwner($chatbot, $actorUserId ?? (int) $chatbot->getAttribute('user_id'));
        if (CredentialRedactor::containsSecretKey($configuration)) {
            throw ValidationException::withMessages(['configuration' => 'Secret values belong in the encrypted credentials payload, not configuration.']);
        }
        $credentials = $this->normaliseCredentials($provider, $credentials);
        $existing = CommerceCredential::query()->where('chatbot_id', $chatbot->getKey())->where('provider', $provider)->first();
        $version = ((int) ($existing?->version ?? 0)) + 1;
        $record = CommerceCredential::query()->updateOrCreate(
            ['chatbot_id' => (int) $chatbot->getKey(), 'provider' => $provider],
            [
                'owner_user_id' => (int) $chatbot->getAttribute('user_id'),
                'credentials' => $credentials,
                'configuration' => $configuration,
                'credential_reference' => $credentialReference,
                'status' => 'active',
                'version' => $version,
                'fingerprint' => $this->fingerprint($credentials),
                'rotated_at' => now(),
                'revoked_at' => null,
            ],
        );

        return $record->refresh();
    }

    /** @param array<string,mixed> $credentials @param array<string,mixed> $configuration */
    public function rotate(Chatbot $chatbot, string $provider, array $credentials, array $configuration = [], ?string $credentialReference = null, ?int $actorUserId = null): CommerceCredential
    {
        return $this->store($chatbot, $provider, $credentials, $configuration, $credentialReference, $actorUserId);
    }

    public function revoke(Chatbot $chatbot, string $provider, int $actorUserId): CommerceCredential
    {
        $this->assertOwner($chatbot, $actorUserId);
        $record = $this->record($chatbot, $provider);
        $record->forceFill([
            'credentials' => null,
            'credential_reference' => null,
            'status' => 'revoked',
            'version' => ((int) $record->version) + 1,
            'revoked_at' => now(),
        ])->save();

        return $record->refresh();
    }

    /** @return array<string,mixed> */
    public function resolve(Chatbot $chatbot, string $provider): array
    {
        $record = $this->record($chatbot, $provider);
        if ((string) $record->status !== 'active' || ! is_array($record->credentials) || $record->credentials === []) {
            throw ValidationException::withMessages(['credentials' => ucfirst($provider) . ' credentials are not active.']);
        }

        return $record->credentials;
    }

    /** @return array<string,mixed> */
    public function configuration(Chatbot $chatbot, string $provider): array
    {
        $record = $this->record($chatbot, $provider);

        return is_array($record->configuration) ? $record->configuration : [];
    }

    /** @return array<string,mixed> */
    public function testConnection(Chatbot $chatbot, string $provider, int $actorUserId): array
    {
        $this->assertOwner($chatbot, $actorUserId);
        $provider = $this->provider($provider);
        $record = $this->record($chatbot, $provider);
        try {
            $credentials = $this->resolve($chatbot, $provider);
            $configuration = is_array($record->configuration) ? $record->configuration : [];
            $domain = trim((string) ($configuration['domain'] ?? $chatbot->getAttribute($provider === 'shopify' ? 'shopify_domain' : 'woocommerce_domain')));
            if ($domain === '') {
                throw ValidationException::withMessages(['domain' => 'A store domain is required.']);
            }
            if ($provider === 'shopify') {
                $handler = new ShopifyToolHandler($domain, (string) $credentials['access_token']);
                $result = $handler->handleToolCall('getProducts', ['query' => '', 'orderby' => 'best_selling', 'order' => 'desc']);
                $success = ! isset($result['error']);
            } else {
                $handler = new WooCommerceToolHandler($domain, (string) $credentials['consumer_key'], (string) $credentials['consumer_secret']);
                $success = is_array($handler->getProducts(['per_page' => 1]));
            }
            $record->forceFill(['last_test_succeeded' => $success, 'last_test_error_hash' => null, 'last_tested_at' => now()])->save();

            return ['provider' => $provider, 'connected' => $success, 'tested_at' => now()->toIso8601String()];
        } catch (Throwable $exception) {
            $record->forceFill([
                'last_test_succeeded' => false,
                'last_test_error_hash' => hash('sha256', $exception::class . ':' . $exception->getCode() . ':' . $exception->getMessage()),
                'last_tested_at' => now(),
            ])->save();

            return ['provider' => $provider, 'connected' => false, 'tested_at' => now()->toIso8601String(), 'error' => 'The store connection could not be verified.'];
        }
    }

    /** @param array<string,mixed> $legacy */
    public function storeFromLegacy(Chatbot $chatbot, array $legacy): void
    {
        $shopify = array_filter(['access_token' => (string) Arr::get($legacy, 'shopify_access_token', '')], fn ($value) => trim($value) !== '');
        if ($shopify !== []) {
            $this->store($chatbot, 'shopify', $shopify, ['domain' => (string) $chatbot->getAttribute('shopify_domain')]);
        }
        $woo = array_filter([
            'consumer_key' => (string) Arr::get($legacy, 'woocommerce_consumer_key', ''),
            'consumer_secret' => (string) Arr::get($legacy, 'woocommerce_consumer_secret', ''),
        ], fn ($value) => trim($value) !== '');
        if ($woo !== []) {
            $this->store($chatbot, 'woocommerce', $woo, ['domain' => (string) $chatbot->getAttribute('woocommerce_domain')]);
        }
    }

    /** @return array<string,mixed> */
    public function summary(Chatbot $chatbot, string $provider): array
    {
        $record = CommerceCredential::query()->where('chatbot_id', $chatbot->getKey())->where('provider', $this->provider($provider))->first();
        if (! $record) {
            return ['provider' => $provider, 'configured' => false, 'status' => 'missing'];
        }

        return [
            'uuid' => $record->uuid,
            'provider' => $record->provider,
            'configured' => (string) $record->status === 'active' && is_array($record->credentials) && $record->credentials !== [],
            'status' => $record->status,
            'credential_reference' => $record->credential_reference,
            'version' => (int) $record->version,
            'fingerprint' => $record->fingerprint,
            'last_test_succeeded' => $record->last_test_succeeded,
            'last_tested_at' => $record->last_tested_at?->toIso8601String(),
            'rotated_at' => $record->rotated_at?->toIso8601String(),
            'revoked_at' => $record->revoked_at?->toIso8601String(),
        ];
    }

    private function record(Chatbot $chatbot, string $provider): CommerceCredential
    {
        return CommerceCredential::query()->where('chatbot_id', $chatbot->getKey())->where('provider', $this->provider($provider))->firstOrFail();
    }

    private function provider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, ['shopify', 'woocommerce'], true)) {
            throw ValidationException::withMessages(['provider' => 'Supported credential providers are Shopify and WooCommerce.']);
        }

        return $provider;
    }

    /** @param array<string,mixed> $credentials @return array<string,string> */
    private function normaliseCredentials(string $provider, array $credentials): array
    {
        $required = $provider === 'shopify' ? ['access_token'] : ['consumer_key', 'consumer_secret'];
        $result = [];
        foreach ($required as $key) {
            $value = trim((string) ($credentials[$key] ?? ''));
            if ($value === '') {
                throw ValidationException::withMessages(["credentials.{$key}" => 'This credential value is required.']);
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /** @param array<string,string> $credentials */
    private function fingerprint(array $credentials): string
    {
        return hash('sha256', implode('|', array_map(static fn (string $key, string $value): string => $key . ':' . substr(hash('sha256', $value), -12), array_keys($credentials), $credentials)));
    }

    private function assertOwner(Chatbot $chatbot, int $actorUserId): void
    {
        if ($actorUserId <= 0 || (int) $chatbot->getAttribute('user_id') !== $actorUserId) {
            abort(403);
        }
    }
}
