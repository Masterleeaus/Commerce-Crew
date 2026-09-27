<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\BnplRuntime;
use Illuminate\Console\Command;

final class ExpireBnplOffers extends Command
{
    protected $signature = 'chatbot-ecommerce:bnpl-expire {--limit=500}';
    protected $description = 'Expire stale BNPL offers.';

    public function handle(BnplRuntime $runtime): int
    {
        $count = $runtime->expireDue((int) $this->option('limit'));
        $this->info("Expired {$count} BNPL offer(s).");

        return self::SUCCESS;
    }
}
