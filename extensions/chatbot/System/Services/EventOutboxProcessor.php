<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Events\ExtensionEvent;
use App\Extensions\Chatbot\System\Models\ChatbotEventOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class EventOutboxProcessor
{
    public function __construct(private readonly WebhookDispatcher $webhooks)
    {
    }

    public function process(int $limit = 100): array
    {
        $worker = (string) Str::uuid();
        $claimed = $this->claim($worker, max(1, min($limit, 500)));
        $published = 0;
        $failed = 0;

        foreach ($claimed as $event) {
            try {
                event(new ExtensionEvent($event->event_type, $event->payload ?? []));
                $this->dispatchWebhooks($event);

                $event->forceFill([
                    'status' => 'published',
                    'published_at' => now(),
                    'locked_at' => null,
                    'locked_by' => null,
                    'last_error' => null,
                ])->save();
                $published++;
            } catch (Throwable $exception) {
                $attempts = ((int) $event->attempts) + 1;
                $terminal = $attempts >= (int) config('chatbot.runtime.outbox.max_attempts', 8);
                $delay = min(
                    (int) config('chatbot.runtime.outbox.max_backoff_seconds', 3600),
                    (int) config('chatbot.runtime.outbox.base_backoff_seconds', 15) * (2 ** max(0, $attempts - 1))
                );

                $event->forceFill([
                    'status' => $terminal ? 'failed' : 'pending',
                    'attempts' => $attempts,
                    'available_at' => $terminal ? null : now()->addSeconds($delay),
                    'failed_at' => $terminal ? now() : null,
                    'locked_at' => null,
                    'locked_by' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 65535),
                ])->save();
                $failed++;
            }
        }

        return ['claimed' => $claimed->count(), 'published' => $published, 'failed' => $failed];
    }

    public function releaseStaleLocks(): int
    {
        return ChatbotEventOutbox::query()
            ->where('status', 'processing')
            ->where('locked_at', '<', now()->subSeconds((int) config('chatbot.runtime.outbox.lock_timeout_seconds', 300)))
            ->update([
                'status' => 'pending',
                'locked_at' => null,
                'locked_by' => null,
                'available_at' => now(),
            ]);
    }

    public function prune(): int
    {
        return ChatbotEventOutbox::query()
            ->whereIn('status', ['published', 'failed'])
            ->where('updated_at', '<', now()->subDays((int) config('chatbot.runtime.event_retention_days', 30)))
            ->delete();
    }

    private function claim(string $worker, int $limit)
    {
        return DB::transaction(function () use ($worker, $limit) {
            $events = ChatbotEventOutbox::query()
                ->where('status', 'pending')
                ->where(function ($query): void {
                    $query->whereNull('available_at')->orWhere('available_at', '<=', now());
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            if ($events->isNotEmpty()) {
                ChatbotEventOutbox::query()->whereKey($events->modelKeys())->update([
                    'status' => 'processing',
                    'locked_at' => now(),
                    'locked_by' => $worker,
                ]);
                $events->each->forceFill(['status' => 'processing', 'locked_at' => now(), 'locked_by' => $worker]);
            }

            return $events;
        });
    }

    private function dispatchWebhooks(ChatbotEventOutbox $event): void
    {
        foreach ((array) config('chatbot.runtime.webhooks', []) as $webhook) {
            if (! is_array($webhook) || empty($webhook['url']) || ($webhook['enabled'] ?? true) === false) {
                continue;
            }
            $events = $webhook['events'] ?? ['*'];
            if (! in_array('*', $events, true) && ! in_array($event->event_type, $events, true)) {
                continue;
            }

            $result = $this->webhooks->dispatch(
                (string) $webhook['url'],
                $event->event_type,
                array_merge($event->payload ?? [], ['event_uuid' => $event->event_uuid]),
                (string) ($webhook['secret'] ?? '')
            );

            if (! $result['successful']) {
                throw new \RuntimeException('Webhook delivery failed with HTTP ' . $result['status']);
            }
        }
    }
}
