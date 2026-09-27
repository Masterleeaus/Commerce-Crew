<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\CartRuntime;
use Illuminate\Console\Command;

class ExpireCarts extends Command
{
    protected $signature = 'chatbot-ecommerce:carts-expire {--limit=500 : Maximum carts to process}';

    protected $description = 'Expire stale native ecommerce carts and release their active inventory reservations.';

    public function handle(CartRuntime $runtime): int
    {
        $expired = $runtime->expireDue((int) $this->option('limit'));
        $this->info(sprintf('Expired %d cart(s).', $expired));

        return self::SUCCESS;
    }
}
