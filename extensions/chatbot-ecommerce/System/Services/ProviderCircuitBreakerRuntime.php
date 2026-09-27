<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\ProviderCircuitBreaker;
use App\Extensions\ChatbotEcommerce\System\Support\CircuitBreakerDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class ProviderCircuitBreakerRuntime
{
    public function assertAvailable(?int $chatbotId, string $provider, string $operation): void
    {
        if (! Schema::hasTable('ext_chatbot_provider_circuit_breakers')) return;
        $breaker = $this->find($chatbotId, $provider, $operation);
        if ($breaker === null) return;
        $decision = CircuitBreakerDecision::evaluate((string) $breaker->state, (int) $breaker->failure_count, $breaker->opened_until?->getTimestamp());
        if (! $decision['allow']) {
            throw new RuntimeException("Provider circuit is open; retry after {$decision['retry_after']} seconds.");
        }
        if ($decision['state'] === 'half_open' && $breaker->state !== 'half_open') {
            $breaker->forceFill(['state' => 'half_open'])->save();
        }
    }

    public function success(?int $chatbotId, string $provider, string $operation): void
    {
        if (! Schema::hasTable('ext_chatbot_provider_circuit_breakers')) return;
        $this->record($chatbotId, $provider, $operation, static function (ProviderCircuitBreaker $breaker): void {
            $breaker->forceFill([
                'state' => 'closed', 'failure_count' => 0, 'opened_at' => null,
                'opened_until' => null, 'last_failure_hash' => null, 'last_success_at' => now(),
            ])->save();
        });
    }

    public function failure(?int $chatbotId, string $provider, string $operation, Throwable $exception): void
    {
        if (! Schema::hasTable('ext_chatbot_provider_circuit_breakers')) return;
        $threshold = max(1, (int) config('chatbot-ecommerce.reliability.circuit_breaker.failure_threshold', 5));
        $cooldown = max(10, (int) config('chatbot-ecommerce.reliability.circuit_breaker.cooldown_seconds', 120));
        $this->record($chatbotId, $provider, $operation, static function (ProviderCircuitBreaker $breaker) use ($threshold, $cooldown, $exception): void {
            $failures = (int) $breaker->failure_count + 1;
            $open = $failures >= $threshold;
            $breaker->forceFill([
                'state' => $open ? 'open' : 'closed',
                'failure_count' => $failures,
                'last_failure_hash' => hash('sha256', $exception::class . ':' . $exception->getMessage()),
                'last_failure_at' => now(),
                'opened_at' => $open ? now() : $breaker->opened_at,
                'opened_until' => $open ? now()->addSeconds($cooldown) : null,
            ])->save();
        });
    }

    private function find(?int $chatbotId, string $provider, string $operation): ?ProviderCircuitBreaker
    {
        return ProviderCircuitBreaker::query()->where('chatbot_id', $chatbotId)->where('provider', $provider)->where('operation', $operation)->first();
    }

    private function record(?int $chatbotId, string $provider, string $operation, callable $callback): void
    {
        DB::transaction(function () use ($chatbotId, $provider, $operation, $callback): void {
            $breaker = ProviderCircuitBreaker::query()->where('chatbot_id', $chatbotId)->where('provider', $provider)->where('operation', $operation)->lockForUpdate()->first();
            if ($breaker === null) {
                $breaker = ProviderCircuitBreaker::query()->create(['chatbot_id' => $chatbotId, 'provider' => $provider, 'operation' => $operation, 'state' => 'closed']);
            }
            $callback($breaker);
        });
    }
}
