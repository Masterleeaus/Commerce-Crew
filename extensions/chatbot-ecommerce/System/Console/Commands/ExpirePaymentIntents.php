<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\PaymentRuntime;
use Illuminate\Console\Command;

final class ExpirePaymentIntents extends Command
{
    protected $signature = 'chatbot-ecommerce:payments-expire {--limit=500}';
    protected $description = 'Expire stale native commerce and rental/hire payment intents.';

    public function handle(PaymentRuntime $payments): int
    {
        $count = $payments->expireDue((int) $this->option('limit'));
        $this->info("Expired {$count} payment intent(s).");
        return self::SUCCESS;
    }
}
