<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotEventOutbox;
use Illuminate\Support\Str;

class EventOutbox
{
    public function record(string $eventType, array $payload, ?string $aggregateType = null, string|int|null $aggregateId = null): ChatbotEventOutbox
    {
        return ChatbotEventOutbox::query()->create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId === null ? null : (string) $aggregateId,
            'payload' => $payload,
            'status' => 'pending',
            'available_at' => now(),
        ]);
    }
}
