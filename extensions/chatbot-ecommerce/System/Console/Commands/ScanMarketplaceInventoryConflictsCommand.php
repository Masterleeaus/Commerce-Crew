<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceInventoryReconciliationRuntime;
use Illuminate\Console\Command;

final class ScanMarketplaceInventoryConflictsCommand extends Command
{
    protected $signature='chatbot-ecommerce:marketplace-scan-inventory {--chatbot=} {--provider=} {--connection=} {--limit=100}';
    protected $description='Scan reservation-aware internal inventory against mapped marketplace listing quantities.';
    public function handle(MarketplaceInventoryReconciliationRuntime $runtime): int
    {
        $query=MarketplaceConnection::query()->where('active',true)->orderBy('id');
        if ($this->option('chatbot')) $query->where('chatbot_id',(int)$this->option('chatbot'));
        if ($this->option('provider')) $query->where('provider',strtolower((string)$this->option('provider')));
        if ($this->option('connection')) $query->where('id',(int)$this->option('connection'));
        $processed=0; $conflicts=0;
        foreach ($query->limit(min(1000,max(1,(int)$this->option('limit'))))->get() as $connection) {
            $chatbot=Chatbot::query()->find($connection->chatbot_id); if ($chatbot===null) continue;
            $run=$runtime->scan($chatbot,$connection,(int)$connection->owner_user_id); $processed++; $conflicts+=(int)$run->conflict_count;
        }
        $this->info("Processed {$processed} marketplace connection(s); detected {$conflicts} active conflict observation(s).");
        return self::SUCCESS;
    }
}
