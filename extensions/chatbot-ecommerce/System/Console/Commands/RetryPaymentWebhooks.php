<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Jobs\ProcessPaymentWebhook;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentWebhookEvent;
use Illuminate\Console\Command;

final class RetryPaymentWebhooks extends Command
{
    protected $signature = 'chatbot-ecommerce:payment-webhooks-retry {--limit=500}';
    protected $description = 'Queue received or retryable failed payment webhook events.';

    public function handle(): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 5000);
        $events = PaymentWebhookEvent::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['received', 'failed'])
                    ->orWhere(function ($stale): void {
                        $stale->where('status', 'queued')->where('queued_at', '<=', now()->subMinutes(5));
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')->limit($limit)->get();

        foreach ($events as $event) {
            $event->forceFill(['status' => 'queued', 'queued_at' => now()])->save();
            ProcessPaymentWebhook::dispatch((int) $event->id);
        }
        $this->info("Queued {$events->count()} payment webhook event(s).");
        return self::SUCCESS;
    }
}
