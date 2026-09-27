<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\CheckoutRuntime;
use Illuminate\Console\Command;

final class ExpireCheckoutSessions extends Command
{
    protected $signature = 'chatbot-ecommerce:expire-checkouts {--limit=500}';
    protected $description = 'Expire stale ecommerce checkout sessions and release their reservations.';

    public function handle(CheckoutRuntime $runtime): int
    {
        $count = $runtime->expireDue((int) $this->option('limit'));
        $this->info(sprintf('Expired %d checkout session(s).', $count));
        return self::SUCCESS;
    }
}
