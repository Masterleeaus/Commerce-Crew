<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Contracts\MessageRuntimeInterface;
use App\Extensions\Chatbot\System\Events\MessageCreated;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotDeliveryReceipt;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageRuntime implements MessageRuntimeInterface
{
    private const STATUS_RANK = ['queued' => 10, 'sent' => 20, 'delivered' => 30, 'read' => 40, 'failed' => 50];

    public function __construct(private readonly EventOutbox $outbox)
    {
    }

    public function create(ChatbotConversation $conversation, array $payload): ChatbotHistory
    {
        return DB::transaction(function () use ($conversation, $payload): ChatbotHistory {
            $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
            if ($idempotencyKey !== '') {
                $existing = ChatbotHistory::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $payload['conversation_id'] = $conversation->id;
            $payload['chatbot_id'] ??= $conversation->chatbot_id;
            $payload['message_id'] ??= (string) Str::uuid();
            $payload['delivery_status'] ??= 'queued';
            $payload['idempotency_key'] = $idempotencyKey !== '' ? $idempotencyKey : null;
            $payload['created_at'] ??= now();

            $message = ChatbotHistory::query()->create($payload);
            $conversation->forceFill(['last_activity_at' => now()])->save();

            event(new MessageCreated($message->id, $conversation->id));
            $this->outbox->record('chatbot.message.created', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'delivery_status' => $message->delivery_status,
            ], ChatbotHistory::class, $message->id);

            return $message;
        });
    }

    public function retry(ChatbotHistory $message): ChatbotHistory
    {
        if ($message->delivery_status !== 'failed') {
            throw ValidationException::withMessages(['message' => 'Only failed messages can be retried.']);
        }

        $message->forceFill([
            'delivery_status' => 'queued',
            'failure_reason' => null,
            'failed_at' => null,
            'retry_count' => ((int) $message->retry_count) + 1,
        ])->save();

        $this->recordReceipt($message, 'queued', ['reason' => 'retry']);

        return $message->refresh();
    }

    public function markSent(ChatbotHistory $message, ?string $providerId = null): ChatbotHistory
    {
        return $this->transition($message, 'sent', [
            'sent_at' => $message->sent_at ?: now(),
            'provider_message_id' => $providerId ?: $message->provider_message_id,
        ]);
    }

    public function markDelivered(ChatbotHistory $message, ?string $providerId = null): ChatbotHistory
    {
        return $this->transition($message, 'delivered', [
            'sent_at' => $message->sent_at ?: now(),
            'delivered_at' => $message->delivered_at ?: now(),
            'provider_message_id' => $providerId ?: $message->provider_message_id,
        ]);
    }

    public function markRead(ChatbotHistory $message): ChatbotHistory
    {
        return $this->transition($message, 'read', [
            'sent_at' => $message->sent_at ?: now(),
            'delivered_at' => $message->delivered_at ?: now(),
            'read_at' => $message->read_at ?: now(),
        ]);
    }

    public function markFailed(ChatbotHistory $message, string $reason, array $payload = []): ChatbotHistory
    {
        if ($message->delivery_status === 'read') {
            return $message->refresh();
        }

        $message->forceFill([
            'delivery_status' => 'failed',
            'failure_reason' => $reason,
            'failed_at' => now(),
        ])->save();
        $this->recordReceipt($message, 'failed', array_merge($payload, ['reason' => $reason]));

        return $message->refresh();
    }

    private function transition(ChatbotHistory $message, string $status, array $attributes): ChatbotHistory
    {
        $current = (string) ($message->delivery_status ?: 'queued');
        if ($current === 'failed') {
            throw ValidationException::withMessages(['message' => 'Retry a failed message before advancing its delivery state.']);
        }
        if ((self::STATUS_RANK[$status] ?? 0) <= (self::STATUS_RANK[$current] ?? 0)) {
            return $message->refresh();
        }

        $message->forceFill(array_merge($attributes, [
            'delivery_status' => $status,
            'failure_reason' => null,
            'failed_at' => null,
        ]))->save();
        $this->recordReceipt($message, $status);

        return $message->refresh();
    }

    private function recordReceipt(ChatbotHistory $message, string $status, array $payload = []): void
    {
        ChatbotDeliveryReceipt::query()->create([
            'history_id' => $message->id,
            'provider_message_id' => $message->provider_message_id,
            'status' => $status,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        $this->outbox->record('chatbot.message.delivery.' . $status, [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'status' => $status,
        ], ChatbotHistory::class, $message->id);
    }
}
