<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;
use App\Extensions\Chatbot\System\Models\ChatbotParticipant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParticipantRuntime
{
    public function list(ChatbotConversation $conversation): Collection
    {
        return ChatbotParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('type')
            ->orderBy('display_name')
            ->get();
    }

    public function upsert(ChatbotConversation $conversation, array $data): ChatbotParticipant
    {
        return DB::transaction(function () use ($conversation, $data): ChatbotParticipant {
            $reference = isset($data['reference']) && trim((string) $data['reference']) !== ''
                ? trim((string) $data['reference'])
                : null;

            $participant = ChatbotParticipant::query()->updateOrCreate([
                'conversation_id' => $conversation->id,
                'type' => $data['type'],
                'reference' => $reference,
            ], [
                'display_name' => $data['display_name'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'last_seen_at' => now(),
            ]);

            return $participant->refresh();
        });
    }

    public function markRead(ChatbotConversation $conversation, ChatbotParticipant $participant, ?int $historyId = null): ChatbotParticipant
    {
        $this->assertBelongsToConversation($conversation, $participant);

        $historyId ??= (int) ChatbotHistory::query()
            ->where('conversation_id', $conversation->id)
            ->max('id');

        if ($historyId > 0) {
            $exists = ChatbotHistory::query()
                ->where('conversation_id', $conversation->id)
                ->whereKey($historyId)
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['history_id' => 'The selected message does not belong to this conversation.']);
            }
        }

        $participant->forceFill([
            'last_read_history_id' => $historyId > 0 ? $historyId : null,
            'last_read_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return $participant->refresh();
    }

    public function unreadCount(ChatbotConversation $conversation, ChatbotParticipant $participant): int
    {
        $this->assertBelongsToConversation($conversation, $participant);

        return ChatbotHistory::query()
            ->where('conversation_id', $conversation->id)
            ->when($participant->last_read_history_id, fn ($query, $id) => $query->where('id', '>', $id))
            ->count();
    }

    public function remove(ChatbotConversation $conversation, ChatbotParticipant $participant): void
    {
        $this->assertBelongsToConversation($conversation, $participant);
        $participant->delete();
    }

    private function assertBelongsToConversation(ChatbotConversation $conversation, ChatbotParticipant $participant): void
    {
        abort_unless((int) $participant->conversation_id === (int) $conversation->id, 404);
    }
}
