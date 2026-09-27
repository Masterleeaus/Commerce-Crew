<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Jobs\SearchMarketplaceListings;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceListingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSearch;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSearchResult;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceCacheKey;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceSearchState;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MarketplaceReadRuntime
{
    public function __construct(private readonly MarketplaceProviderRegistry $providers, private readonly MarketplaceCardRuntime $cards) {}

    /** @param array<string,mixed> $filters @param array<int,string> $providerKeys */
    public function createSearch(Chatbot $chatbot, string $sessionId, string $query, array $filters = [], array $providerKeys = []): MarketplaceSearch
    {
        $providerKeys = array_values(array_unique(array_map('strtolower', array_filter($providerKeys))));
        if ($providerKeys === []) {
            $providerKeys = MarketplaceConnection::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('active', true)->pluck('provider')->unique()->values()->all();
        }
        $cacheKey = MarketplaceCacheKey::make((int) $chatbot->getAttribute('id'), $query, $filters, $providerKeys);
        $cached = $this->cachedSearch((int) $chatbot->getAttribute('id'), $cacheKey);
        if ($cached !== null) {
            $cached->forceFill(['cache_hit' => true])->save();
            return $cached->load('results');
        }
        $ttl = max(30, (int) config('chatbot-ecommerce.marketplaces.search_cache_ttl_seconds', config('chatbot-ecommerce.search_cache_ttl_seconds', 300)));
        $search = MarketplaceSearch::query()->create([
            'chatbot_id' => (int) $chatbot->getAttribute('id'), 'session_id' => $sessionId, 'cache_key' => $cacheKey,
            'query' => trim($query), 'filters' => $filters, 'providers' => $providerKeys, 'status' => MarketplaceSearchState::QUEUED,
            'provider_count' => count($providerKeys), 'expires_at' => now()->addSeconds($ttl),
        ]);
        if (config('chatbot-ecommerce.marketplaces.dispatch_mode', 'sync') === 'async') {
            SearchMarketplaceListings::dispatch($search->id);
            return $search;
        }
        return $this->executeSearch($search);
    }

    public function cachedSearch(int $chatbotId, string $cacheKey): ?MarketplaceSearch
    {
        return MarketplaceSearch::query()->where('chatbot_id', $chatbotId)->where('cache_key', $cacheKey)
            ->where('status', MarketplaceSearchState::COMPLETED)->where('expires_at', '>', now())->latest('id')->with('results')->first();
    }

    public function executeSearch(MarketplaceSearch $search): MarketplaceSearch
    {
        if (! in_array($search->status, [MarketplaceSearchState::QUEUED, MarketplaceSearchState::SEARCHING, MarketplaceSearchState::PARTIAL], true)) {
            return $search->load('results');
        }
        $search->forceFill(['status' => MarketplaceSearchState::SEARCHING, 'started_at' => $search->started_at ?: now(), 'errors' => []])->save();
        $providerKeys = (array) $search->providers;
        $connections = MarketplaceConnection::query()->where('chatbot_id', (int) $search->chatbot_id)->where('active', true)
            ->when($providerKeys !== [], fn ($q) => $q->whereIn('provider', $providerKeys))->orderBy('id')->get();
        $errors = [];
        $position = 0;
        $processed = 0;
        $successes = 0;
        $maximumResults = max(1, (int) config('chatbot-ecommerce.marketplaces.maximum_results_per_provider', 50));
        $search->forceFill(['provider_count' => $connections->count(), 'completed_provider_count' => 0])->save();
        foreach ($connections as $connection) {
            try {
                $response = $this->providers->get((string) $connection->provider)->searchListings($connection, ['query' => $search->query, 'filters' => (array) $search->filters]);
                $successes++;
                foreach (array_slice((array) ($response['items'] ?? []), 0, $maximumResults) as $item) {
                    if (! is_array($item) || ($item['external_listing_id'] ?? '') === '') { continue; }
                    $position++;
                    MarketplaceSearchResult::query()->updateOrCreate(
                        ['search_id' => $search->id, 'provider' => $item['provider'], 'external_listing_id' => $item['external_listing_id']],
                        array_merge($item, ['connection_id' => $connection->id, 'position' => $position])
                    );
                    $snapshot = MarketplaceListingSnapshot::query()->firstOrNew(['connection_id' => $connection->id, 'external_listing_id' => $item['external_listing_id']]);
                    $snapshot->forceFill(['chatbot_id' => $search->chatbot_id, 'provider' => $item['provider'], 'source_hash' => $item['source_hash'], 'snapshot' => $item, 'first_seen_at' => $snapshot->exists ? $snapshot->first_seen_at : now(), 'last_seen_at' => now()])->save();
                }
                $connection->forceFill(['last_read_at' => now(), 'last_error' => null])->save();
            } catch (Throwable $e) {
                $errors[] = ['provider' => $connection->provider, 'connection_uuid' => $connection->uuid, 'message' => 'Marketplace results are temporarily unavailable.', 'error_hash' => hash('sha256', $e->getMessage())];
                $connection->forceFill(['last_error' => 'Read failed: ' . hash('sha256', $e->getMessage())])->save();
            }
            $processed++;
            $search->forceFill([
                'completed_provider_count' => $processed,
                'result_count' => MarketplaceSearchResult::query()->where('search_id', $search->id)->count(),
                'status' => MarketplaceSearchState::PARTIAL,
                'errors' => $errors,
            ])->save();
        }
        $count = MarketplaceSearchResult::query()->where('search_id', $search->id)->count();
        $search->forceFill([
            'status' => $successes > 0 ? MarketplaceSearchState::COMPLETED : MarketplaceSearchState::FAILED,
            'result_count' => $count, 'completed_provider_count' => $connections->count(), 'completed_at' => now(), 'errors' => $errors,
        ])->save();
        return $search->fresh()->load('results');
    }

    public function listing(Chatbot $chatbot, string $provider, string $externalListingId): ?MarketplaceListingSnapshot
    {
        return MarketplaceListingSnapshot::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('provider', strtolower($provider))
            ->where('external_listing_id', $externalListingId)->latest('last_seen_at')->first();
    }

    /** @return array<string,mixed> */
    public function card(MarketplaceSearch $search): array { return $this->cards->search($search); }
}
