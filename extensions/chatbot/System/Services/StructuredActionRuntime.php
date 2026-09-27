<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;
use App\Extensions\Chatbot\System\Models\ChatbotStructuredAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StructuredActionRuntime
{
    public function list(ChatbotConversation $conversation, ?string $status = null): Collection
    {
        return ChatbotStructuredAction::query()
            ->where('conversation_id', $conversation->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')->get();
    }

    public function create(ChatbotConversation $conversation, array $data): ChatbotStructuredAction
    {
        if (! empty($data['history_id'])) {
            $valid = ChatbotHistory::query()->whereKey($data['history_id'])
                ->where('conversation_id', $conversation->id)->exists();
            if (! $valid) throw ValidationException::withMessages(['history_id' => 'The selected message does not belong to this conversation.']);
        }

        if (! empty($data['idempotency_key'])) {
            $existing = ChatbotStructuredAction::query()->where('conversation_id', $conversation->id)
                ->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) return $existing;
        }

        return ChatbotStructuredAction::query()->create([
            'conversation_id' => $conversation->id,
            'history_id' => $data['history_id'] ?? null,
            'type' => $data['type'],
            'status' => 'pending',
            'payload' => $data['payload'] ?? [],
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public function approve(ChatbotConversation $conversation, ChatbotStructuredAction $action, ?int $userId): ChatbotStructuredAction
    {
        return $this->transition($conversation, $action, ['pending'], 'approved', [
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
        ]);
    }

    public function reject(ChatbotConversation $conversation, ChatbotStructuredAction $action, ?int $userId, ?string $reason): ChatbotStructuredAction
    {
        return $this->transition($conversation, $action, ['pending'], 'rejected', [
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function execute(ChatbotConversation $conversation, ChatbotStructuredAction $action, array $result): ChatbotStructuredAction
    {
        return $this->transition($conversation, $action, ['approved'], 'executed', [
            'result' => $result,
            'executed_at' => now(),
        ]);
    }

    public function cancel(ChatbotConversation $conversation, ChatbotStructuredAction $action): ChatbotStructuredAction
    {
        return $this->transition($conversation, $action, ['pending', 'approved'], 'cancelled', ['cancelled_at' => now()]);
    }

    private function transition(ChatbotConversation $conversation, ChatbotStructuredAction $action, array $allowed, string $status, array $values): ChatbotStructuredAction
    {
        abort_unless((int) $action->conversation_id === (int) $conversation->id, 404);

        return DB::transaction(function () use ($action, $allowed, $status, $values) {
            $locked = ChatbotStructuredAction::query()->lockForUpdate()->findOrFail($action->id);
            if ($locked->expires_at && $locked->expires_at->isPast() && $locked->status === 'pending') {
                $locked->update(['status' => 'expired']);
                throw ValidationException::withMessages(['action' => 'This action has expired.']);
            }
            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages(['action' => "Cannot transition action from {$locked->status} to {$status}."]);
            }
            $locked->fill($values + ['status' => $status])->save();
            return $locked->fresh();
        });
    }
}
