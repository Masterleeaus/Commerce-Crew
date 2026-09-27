<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Contracts\StreamingRuntimeInterface;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotStreamEvent;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StreamingRuntime implements StreamingRuntimeInterface
{
    public function publish(string $channel, string $event, array $payload): void
    {
        $stored = $this->persist($channel, $event, $payload);

        event('chatbot.stream', [
            'channel' => $channel,
            'event' => $event,
            'payload' => $payload,
            'cursor' => $stored?->id,
            'event_uuid' => $stored?->event_uuid,
        ]);
    }

    public function typing(int $conversationId, string $participant, bool $active): void
    {
        $this->publish("conversation.$conversationId", 'typing', compact('participant', 'active'));
    }

    public function presence(string $participant, bool $online): void
    {
        $this->publish("presence.$participant", 'presence', compact('participant', 'online'));
    }

    public function replay(ChatbotConversation $conversation, ?int $afterId = null, ?string $event = null, int $limit = 100): array
    {
        if (! Schema::hasTable('ext_chatbot_stream_events')) {
            return ['data' => [], 'next_cursor' => $afterId, 'has_more' => false];
        }

        $limit = max(1, min($limit, (int) config('chatbot.runtime.streaming.max_replay_events', 250)));
        $query = ChatbotStreamEvent::query()
            ->where('conversation_id', $conversation->id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($afterId !== null, fn ($query) => $query->where('id', '>', $afterId))
            ->when($event !== null && $event !== '', fn ($query) => $query->where('event', $event))
            ->orderBy('id');

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        return [
            'data' => $rows,
            'next_cursor' => $rows->last()?->id ?? $afterId,
            'has_more' => $hasMore,
        ];
    }

    public function prune(?int $retentionHours = null): int
    {
        if (! Schema::hasTable('ext_chatbot_stream_events')) {
            return 0;
        }

        $hours = max(1, $retentionHours ?? (int) config('chatbot.runtime.streaming.retention_hours', 24));

        return ChatbotStreamEvent::query()
            ->where(function ($query) use ($hours): void {
                $query->where('expires_at', '<=', now())
                    ->orWhere('created_at', '<', now()->subHours($hours));
            })
            ->delete();
    }

    private function persist(string $channel, string $event, array $payload): ?ChatbotStreamEvent
    {
        if (! Schema::hasTable('ext_chatbot_stream_events')) {
            return null;
        }

        $conversationId = $this->conversationIdFromChannel($channel);
        if ($conversationId === null || ! ChatbotConversation::query()->whereKey($conversationId)->exists()) {
            return null;
        }

        $ttl = max(60, (int) config('chatbot.runtime.streaming.event_ttl_seconds', 3600));

        return ChatbotStreamEvent::query()->create([
            'event_uuid' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'channel' => $channel,
            'event' => $event,
            'payload' => $payload,
            'expires_at' => now()->addSeconds($ttl),
        ]);
    }

    private function conversationIdFromChannel(string $channel): ?int
    {
        if (! preg_match('/^conversation\.(\d+)$/', $channel, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
