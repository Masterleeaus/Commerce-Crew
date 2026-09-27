<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Jobs;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Queue\Middleware\RestoreCommerceTenantContext;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceInventoryReconciliationRuntime;
use App\Extensions\ChatbotEcommerce\System\Support\RetryBackoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ScanMarketplaceInventoryConflicts implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $timeout = 300;
    public int $uniqueFor = 300;
    public function __construct(public readonly int $connectionId) { $this->onQueue('chatbot-ecommerce-inventory-reconciliation'); }
    public function uniqueId(): string { return 'marketplace-inventory-scan:' . $this->connectionId; }
    /** @return list<int> */ public function backoff(): array { return RetryBackoff::schedule($this->tries, 15, 300); }
    /** @return list<object> */ public function middleware(): array { return [app(RestoreCommerceTenantContext::class)]; }
    /** @return array<string,mixed> */
    public function commerceTenantContext(): array
    {
        $connection = MarketplaceConnection::query()->find($this->connectionId);
        return ['chatbot_id' => $connection?->chatbot_id, 'user_id' => $connection?->owner_user_id, 'connection_id' => $this->connectionId];
    }
    public function handle(MarketplaceInventoryReconciliationRuntime $runtime): void
    {
        $connection = MarketplaceConnection::query()->find($this->connectionId);
        if ($connection === null || ! $connection->active) return;
        $chatbot = Chatbot::query()->find($connection->chatbot_id);
        if ($chatbot !== null) $runtime->scan($chatbot, $connection, (int) $connection->owner_user_id);
    }
}
