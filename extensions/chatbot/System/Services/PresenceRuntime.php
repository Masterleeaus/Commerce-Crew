<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotPresence;
use Illuminate\Support\Collection;

class PresenceRuntime
{
    public function touch(ChatbotConversation $conversation, string $participantKey, string $state = 'online', array $metadata = []): ChatbotPresence
    {
        return ChatbotPresence::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'participant_key' => $participantKey],
            ['state' => $state, 'last_seen_at' => now(), 'metadata' => $metadata]
        );
    }

    public function active(ChatbotConversation $conversation): Collection
    {
        $ttl = max(15, (int) config('chatbot.runtime.presence_ttl_seconds', 120));

        return ChatbotPresence::query()
            ->where('conversation_id', $conversation->id)
            ->where('last_seen_at', '>=', now()->subSeconds($ttl))
            ->orderByDesc('last_seen_at')
            ->get();
    }
}
