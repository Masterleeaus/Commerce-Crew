<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Jobs;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceWriteProposal;
use App\Extensions\ChatbotEcommerce\System\Queue\Middleware\RestoreCommerceTenantContext;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceWriteRuntime;
use App\Extensions\ChatbotEcommerce\System\Support\RetryBackoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExecuteMarketplaceWrite implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 900;
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly int $proposalId) { $this->onQueue('chatbot-ecommerce-marketplace-write'); }
    public function uniqueId(): string { return 'marketplace-write:' . $this->proposalId; }
    /** @return list<int> */ public function backoff(): array { return RetryBackoff::schedule($this->tries, 10, 300); }
    /** @return list<object> */ public function middleware(): array { return [app(RestoreCommerceTenantContext::class)]; }
    /** @return array<string,mixed> */
    public function commerceTenantContext(): array
    {
        $proposal = MarketplaceWriteProposal::query()->with('connection')->find($this->proposalId);
        return ['chatbot_id' => $proposal?->chatbot_id ?? $proposal?->connection?->chatbot_id, 'user_id' => $proposal?->approved_by, 'proposal_id' => $this->proposalId];
    }
    public function handle(MarketplaceWriteRuntime $runtime): void
    {
        $proposal = MarketplaceWriteProposal::query()->find($this->proposalId);
        if ($proposal !== null) $runtime->execute($proposal);
    }
}
