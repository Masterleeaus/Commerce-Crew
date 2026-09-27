<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\RentalHireRuntime;
use Illuminate\Console\Command;

final class ExpireRentalHirePaymentRequests extends Command
{
    protected $signature = 'chatbot-ecommerce:rental-hire-expire-payments {--limit=500}';
    protected $description = 'Expire stale pending rental and hire payment requests.';

    public function handle(RentalHireRuntime $runtime): int
    {
        $count = $runtime->expirePaymentRequests((int) $this->option('limit'));
        $this->info("Expired {$count} rental/hire payment request(s).");
        return self::SUCCESS;
    }
}
