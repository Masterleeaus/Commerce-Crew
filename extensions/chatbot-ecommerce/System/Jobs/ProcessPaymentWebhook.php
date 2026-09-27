<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Jobs;

use App\Extensions\ChatbotEcommerce\System\Models\PaymentWebhookEvent;
use App\Extensions\ChatbotEcommerce\System\Queue\Middleware\RestoreCommerceTenantContext;
use App\Extensions\ChatbotEcommerce\System\Services\PaymentRuntime;
use App\Extensions\ChatbotEcommerce\System\Support\RetryBackoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessPaymentWebhook implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 90;
    public int $uniqueFor = 900;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('chatbot-ecommerce-payment-webhooks');
    }

    public function uniqueId(): string
    {
        return 'payment-webhook:' . $this->eventId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return RetryBackoff::schedule($this->tries, 5, 300);
    }

    /** @return array<string,mixed> */
    public function commerceTenantContext(): array
    {
        $event = PaymentWebhookEvent::query()->with('intent')->find($this->eventId);
        return ['chatbot_id' => $event?->intent?->chatbot_id, 'payment_webhook_event_id' => $this->eventId];
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [app(RestoreCommerceTenantContext::class)];
    }

    public function handle(PaymentRuntime $runtime): void
    {
        $event = PaymentWebhookEvent::query()->find($this->eventId);
        if ($event !== null) $runtime->processReceivedWebhook($event);
    }

    public function failed(?Throwable $exception): void
    {
        $event = PaymentWebhookEvent::query()->find($this->eventId);
        if ($event !== null && (string) $event->status !== 'processed') {
            $event->forceFill([
                'status' => 'dead_lettered',
                'dead_lettered_at' => now(),
                'error_message' => $exception ? substr($exception->getMessage(), 0, 2000) : 'Payment webhook exhausted all retries.',
            ])->save();
        }
    }
}
