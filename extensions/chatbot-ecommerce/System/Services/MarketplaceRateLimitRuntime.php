<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceRateLimit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MarketplaceRateLimitRuntime
{
    public function consume(MarketplaceConnection $connection, string $operation): MarketplaceRateLimit
    {
        return DB::transaction(function () use ($connection, $operation): MarketplaceRateLimit {
            $now = now();
            $limitValue = max(1, (int) config('chatbot-ecommerce.marketplaces.write.maximum_writes_per_minute', 10));
            $row = MarketplaceRateLimit::query()
                ->where('connection_id', $connection->id)
                ->where('operation', $operation)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $row = MarketplaceRateLimit::query()->create([
                    'connection_id' => $connection->id,
                    'operation' => $operation,
                    'window_started_at' => $now->copy()->startOfMinute(),
                    'request_count' => 0,
                    'limit' => $limitValue,
                    'remaining' => $limitValue,
                    'reset_at' => $now->copy()->startOfMinute()->addMinute(),
                ]);
            }

            if ($row->blocked_until !== null && $row->blocked_until->isFuture()) {
                throw ValidationException::withMessages([
                    'marketplace' => 'Marketplace writes are temporarily paused because the provider rate limit is active.',
                ]);
            }

            if ($row->window_started_at === null || $row->window_started_at->lt($now->copy()->subMinute())) {
                $row->forceFill([
                    'window_started_at' => $now->copy()->startOfMinute(),
                    'request_count' => 0,
                    'limit' => $limitValue,
                    'remaining' => $limitValue,
                    'reset_at' => $now->copy()->startOfMinute()->addMinute(),
                    'blocked_until' => null,
                ]);
            }

            $nextCount = (int) $row->request_count + 1;
            if ($nextCount > $limitValue) {
                $row->forceFill([
                    'request_count' => $nextCount,
                    'remaining' => 0,
                    'blocked_until' => $row->reset_at ?: $now->copy()->addMinute(),
                ])->save();
                throw ValidationException::withMessages([
                    'marketplace' => 'The safe marketplace write limit has been reached. Try again after the current rate-limit window resets.',
                ]);
            }

            $row->forceFill([
                'request_count' => $nextCount,
                'limit' => $limitValue,
                'remaining' => max(0, $limitValue - $nextCount),
            ])->save();

            return $row->fresh();
        });
    }

    /** @param array<string,mixed> $providerState */
    public function recordProviderState(MarketplaceConnection $connection, string $operation, array $providerState): void
    {
        if ($providerState === []) {
            return;
        }
        $row = MarketplaceRateLimit::query()->firstOrCreate(
            ['connection_id' => $connection->id, 'operation' => $operation],
            ['window_started_at' => now()->startOfMinute(), 'request_count' => 0, 'limit' => 0]
        );
        $changes = ['metadata' => array_replace_recursive((array) $row->metadata, ['provider' => $providerState])];
        if (isset($providerState['limit'])) {
            $changes['limit'] = max(0, (int) $providerState['limit']);
        }
        if (isset($providerState['remaining'])) {
            $changes['remaining'] = max(0, (int) $providerState['remaining']);
        }
        if (! empty($providerState['reset_at'])) {
            $changes['reset_at'] = $providerState['reset_at'];
        }
        if (! empty($providerState['blocked_until'])) {
            $changes['blocked_until'] = $providerState['blocked_until'];
        }
        $row->forceFill($changes)->save();
    }
}
