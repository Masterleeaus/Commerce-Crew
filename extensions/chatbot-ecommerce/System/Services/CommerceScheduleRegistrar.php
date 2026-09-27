<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

final class CommerceScheduleRegistrar
{
    public function __construct(private readonly CommerceLifecycleRuntime $lifecycle) {}

    public function register(Schedule $schedule): void
    {
        $this->guard($schedule->command('chatbot-ecommerce:expire-reservations --limit=1000')->everyMinute(), 'reservations-expire', 5);
        $this->guard($schedule->command('chatbot-ecommerce:carts-expire --limit=1000')->hourly(), 'carts-expire', 65);
        $this->guard($schedule->command('chatbot-ecommerce:expire-coupon-usages --limit=1000')->everyFiveMinutes(), 'coupon-usages-expire', 10);
        $this->guard($schedule->command('chatbot-ecommerce:expire-checkouts --limit=1000')->everyMinute(), 'checkouts-expire', 5);
        $this->guard($schedule->command('chatbot-ecommerce:expire-shipping-quotes --limit=1000')->everyMinute(), 'shipping-quotes-expire', 5);
        $this->guard($schedule->command('chatbot-ecommerce:rental-hire-generate-charges --limit=1000')->dailyAt('00:10'), 'rental-charges-generate', 120);
        $this->guard($schedule->command('chatbot-ecommerce:rental-hire-expire-payments --limit=1000')->hourlyAt(15), 'rental-payments-expire', 65);
        $this->guard($schedule->command('chatbot-ecommerce:payments-expire --limit=1000')->everyMinute(), 'payments-expire', 5);
        $this->guard($schedule->command('chatbot-ecommerce:bnpl-expire --limit=1000')->everyMinute(), 'bnpl-expire', 5);
        $this->guard($schedule->command('chatbot-ecommerce:contexts-expire --limit=2000')->hourlyAt(25), 'contexts-expire', 65);
        $this->guard($schedule->command('chatbot-ecommerce:payment-webhooks-retry --limit=500')->everyMinute(), 'payment-webhooks-retry', 5);
        $this->guard($schedule->command('chatbot-ecommerce:marketplace-import-orders --limit=100')->hourlyAt(35), 'marketplace-orders-import', 65);
        $this->guard($schedule->command('chatbot-ecommerce:marketplace-scan-inventory --limit=100')->everyFiveMinutes(), 'marketplace-scan-inventory', 10);
    }

    private function guard(Event $event, string $name, int $overlapMinutes): void
    {
        $event->name('chatbot-ecommerce:' . $name)
            ->withoutOverlapping($overlapMinutes)
            ->onOneServer()
            ->when(fn (): bool => $this->lifecycle->isEnabled());
    }
}
