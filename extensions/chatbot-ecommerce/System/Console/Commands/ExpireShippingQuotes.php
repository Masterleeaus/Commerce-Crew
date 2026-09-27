<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\ShippingRuntime;
use Illuminate\Console\Command;

final class ExpireShippingQuotes extends Command
{
    protected $signature = 'chatbot-ecommerce:expire-shipping-quotes {--limit=500}';
    protected $description = 'Expire stale native commerce shipping quotes.';

    public function handle(ShippingRuntime $shipping): int
    {
        $count = $shipping->expireQuotes((int) $this->option('limit'));
        $this->info("Expired {$count} shipping quote(s).");
        return self::SUCCESS;
    }
}
