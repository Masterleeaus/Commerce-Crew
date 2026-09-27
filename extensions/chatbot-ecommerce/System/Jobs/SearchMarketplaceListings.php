<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Jobs;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSearch;
use App\Extensions\ChatbotEcommerce\System\Queue\Middleware\RestoreCommerceTenantContext;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceReadRuntime;
use App\Extensions\ChatbotEcommerce\System\Support\RetryBackoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SearchMarketplaceListings implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $timeout = 120;
    public int $uniqueFor = 300;
    public function __construct(public readonly int $searchId) { $this->onQueue('chatbot-ecommerce-marketplace-read'); }
    public function uniqueId(): string { return 'marketplace-search-' . $this->searchId; }
    /** @return list<int> */ public function backoff(): array { return RetryBackoff::schedule($this->tries, 5, 120); }
    /** @return list<object> */ public function middleware(): array { return [app(RestoreCommerceTenantContext::class)]; }
    /** @return array<string,mixed> */
    public function commerceTenantContext(): array
    {
        $search = MarketplaceSearch::query()->find($this->searchId);
        return ['chatbot_id' => $search?->chatbot_id, 'session_id' => $search?->session_id, 'search_id' => $this->searchId];
    }
    public function handle(MarketplaceReadRuntime $runtime): void
    {
        $search = MarketplaceSearch::query()->find($this->searchId);
        if ($search !== null) $runtime->executeSearch($search);
    }
}
