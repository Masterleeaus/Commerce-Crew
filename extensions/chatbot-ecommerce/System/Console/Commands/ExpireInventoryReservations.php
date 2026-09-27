<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\InventoryRuntime;
use Illuminate\Console\Command;

final class ExpireInventoryReservations extends Command
{
    protected $signature = 'chatbot-ecommerce:expire-reservations {--limit=500}';

    protected $description = 'Release expired native-commerce inventory reservations.';

    public function handle(InventoryRuntime $runtime): int
    {
        $expired = $runtime->expireDue((int) $this->option('limit'));
        $this->info(sprintf('Expired %d inventory reservation(s).', $expired));

        return self::SUCCESS;
    }
}
