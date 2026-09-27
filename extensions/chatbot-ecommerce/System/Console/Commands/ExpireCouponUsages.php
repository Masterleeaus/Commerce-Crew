<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\CouponRuntime;
use Illuminate\Console\Command;

final class ExpireCouponUsages extends Command
{
    protected $signature = 'chatbot-ecommerce:expire-coupon-usages {--limit=500}';

    protected $description = 'Expire stale coupon reservations created by native commerce carts.';

    public function handle(CouponRuntime $runtime): int
    {
        $expired = $runtime->expireDue((int) $this->option('limit'));
        $this->info(sprintf('Expired %d coupon reservation(s).', $expired));

        return self::SUCCESS;
    }
}
