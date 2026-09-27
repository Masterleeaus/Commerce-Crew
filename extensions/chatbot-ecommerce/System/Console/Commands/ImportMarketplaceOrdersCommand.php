<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceOrderImportRuntime;
use Illuminate\Console\Command;
final class ImportMarketplaceOrdersCommand extends Command
{
    protected $signature = 'chatbot-ecommerce:marketplace-import-orders {--chatbot=} {--provider=} {--limit=100}';
    protected $description = 'Import read-only marketplace order snapshots from active connections.';
    public function handle(MarketplaceOrderImportRuntime $runtime): int
    {
        $query = MarketplaceConnection::query()->where('active', true)->orderBy('id');
        if ($this->option('chatbot')) { $query->where('chatbot_id', (int) $this->option('chatbot')); }
        if ($this->option('provider')) { $query->where('provider', strtolower((string) $this->option('provider'))); }
        $count = 0; foreach ($query->limit(max(1, (int) $this->option('limit')))->get() as $connection) { $runtime->import($connection); $count++; }
        $this->info("Processed {$count} marketplace connection(s)."); return self::SUCCESS;
    }
}
