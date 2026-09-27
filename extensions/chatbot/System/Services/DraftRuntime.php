<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotDraft;

class DraftRuntime
{
    public function save(ChatbotConversation $conversation, ?int $userId, array $attributes): ChatbotDraft
    {
        return ChatbotDraft::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $userId],
            [
                'content' => $attributes['content'] ?? null,
                'attachments' => $attributes['attachments'] ?? [],
                'metadata' => $attributes['metadata'] ?? [],
            ]
        );
    }

    public function get(ChatbotConversation $conversation, ?int $userId): ?ChatbotDraft
    {
        return ChatbotDraft::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->first();
    }

    public function delete(ChatbotConversation $conversation, ?int $userId): void
    {
        ChatbotDraft::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->delete();
    }
}
