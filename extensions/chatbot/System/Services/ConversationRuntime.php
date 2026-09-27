<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Contracts\ConversationRuntimeInterface;
use App\Extensions\Chatbot\System\Events\ConversationArchived;
use App\Extensions\Chatbot\System\Events\ConversationMerged;
use App\Extensions\Chatbot\System\Models\ChatbotAttachment;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotDraft;
use App\Extensions\Chatbot\System\Models\ChatbotParticipant;
use App\Extensions\Chatbot\System\Models\ChatbotPresence;
use App\Extensions\Chatbot\System\Models\ChatbotStructuredAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationRuntime implements ConversationRuntimeInterface
{
    public function __construct(
        private readonly AIRuntime $ai,
        private readonly EventOutbox $outbox,
    ) {
    }

    public function create(array $attributes): ChatbotConversation
    {
        $conversation = ChatbotConversation::query()->create($attributes);
        $this->outbox->record('chatbot.conversation.created', ['conversation_id' => $conversation->id], ChatbotConversation::class, $conversation->id);

        return $conversation;
    }

    public function archive(ChatbotConversation $conversation): ChatbotConversation
    {
        $conversation->forceFill(['archived_at' => now()])->save();
        event(new ConversationArchived($conversation->id));
        $this->outbox->record('chatbot.conversation.archived', ['conversation_id' => $conversation->id], ChatbotConversation::class, $conversation->id);

        return $conversation->refresh();
    }

    public function merge(ChatbotConversation $target, ChatbotConversation $source): ChatbotConversation
    {
        if ($target->is($source)) {
            throw ValidationException::withMessages(['source_id' => 'A conversation cannot be merged into itself.']);
        }

        if ((int) $target->chatbot_id !== (int) $source->chatbot_id) {
            throw ValidationException::withMessages(['source_id' => 'Conversations must belong to the same chatbot.']);
        }

        if ($this->wouldCreateMergeCycle($target, $source)) {
            throw ValidationException::withMessages(['source_id' => 'The requested merge would create a merge cycle.']);
        }

        return DB::transaction(function () use ($target, $source): ChatbotConversation {
            $locked = ChatbotConversation::query()
                ->whereKey([$target->id, $source->id])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $target = $locked->get($target->id) ?? $target;
            $source = $locked->get($source->id) ?? $source;

            $source->histories()->update(['conversation_id' => $target->id]);
            ChatbotAttachment::query()->where('conversation_id', $source->id)->update(['conversation_id' => $target->id]);
            ChatbotStructuredAction::query()->where('conversation_id', $source->id)->update(['conversation_id' => $target->id]);
            ChatbotDraft::query()->where('conversation_id', $source->id)->delete();
            ChatbotPresence::query()->where('conversation_id', $source->id)->delete();

            $this->mergeParticipants($target->id, $source->id);

            $source->forceFill([
                'merged_into_id' => $target->id,
                'archived_at' => now(),
            ])->save();

            $target->forceFill([
                'last_activity_at' => collect([$target->last_activity_at, $source->last_activity_at])->filter()->sortDesc()->first() ?: now(),
                'summary' => null,
                'summary_updated_at' => null,
            ])->save();

            event(new ConversationMerged($target->id, $source->id));
            $this->outbox->record('chatbot.conversation.merged', [
                'target_id' => $target->id,
                'source_id' => $source->id,
            ], ChatbotConversation::class, $target->id);

            return $target->refresh();
        });
    }

    public function summarize(ChatbotConversation $conversation, bool $force = false): string
    {
        if (! $force && $conversation->summary) {
            return $conversation->summary;
        }

        $summary = $this->ai->summarize(
            $conversation->histories()->orderBy('id')->get(['role', 'message'])->toArray()
        );

        $conversation->forceFill(['summary' => $summary, 'summary_updated_at' => now()])->save();

        return $summary;
    }

    public function search(array $filters)
    {
        $maximum = max(1, (int) config('chatbot.runtime.max_page_size', 100));
        $perPage = min($maximum, max(1, (int) ($filters['per_page'] ?? 25)));
        $queryText = trim((string) ($filters['q'] ?? ''));

        $query = ChatbotConversation::query()->with(['customer', 'lastMessage']);
        $query->when($queryText !== '', function (Builder $builder) use ($queryText): void {
            $escaped = addcslashes($queryText, '%_\\');
            $builder->where(function (Builder $inner) use ($escaped): void {
                $inner->where('conversation_name', 'like', "%{$escaped}%")
                    ->orWhere('summary', 'like', "%{$escaped}%")
                    ->orWhereHas('histories', fn (Builder $history) => $history->where('message', 'like', "%{$escaped}%"));
            });
        });
        $query->when(array_key_exists('archived', $filters), fn (Builder $builder) => $filters['archived'] ? $builder->whereNotNull('archived_at') : $builder->whereNull('archived_at'));
        $query->when($filters['channel'] ?? null, fn (Builder $builder, $value) => $builder->where('chatbot_channel', $value));
        $query->when($filters['chatbot_id'] ?? null, fn (Builder $builder, $value) => $builder->where('chatbot_id', $value));

        return $query->latest('last_activity_at')->paginate($perPage);
    }

    private function wouldCreateMergeCycle(ChatbotConversation $target, ChatbotConversation $source): bool
    {
        $cursor = $target;
        $seen = [];

        while ($cursor->merged_into_id) {
            if ((int) $cursor->merged_into_id === (int) $source->id) {
                return true;
            }
            if (isset($seen[$cursor->merged_into_id])) {
                return true;
            }
            $seen[$cursor->merged_into_id] = true;
            $cursor = ChatbotConversation::query()->find($cursor->merged_into_id);
            if (! $cursor) {
                break;
            }
        }

        return false;
    }

    private function mergeParticipants(int $targetId, int $sourceId): void
    {
        ChatbotParticipant::query()->where('conversation_id', $sourceId)->get()->each(function (ChatbotParticipant $participant) use ($targetId): void {
            $existing = ChatbotParticipant::query()
                ->where('conversation_id', $targetId)
                ->where('type', $participant->type)
                ->where('reference', $participant->reference)
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'display_name' => $existing->display_name ?: $participant->display_name,
                    'metadata' => array_replace($participant->metadata ?? [], $existing->metadata ?? []),
                    'last_seen_at' => collect([$existing->last_seen_at, $participant->last_seen_at])->filter()->sortDesc()->first(),
                ])->save();
                $participant->delete();
            } else {
                $participant->forceFill(['conversation_id' => $targetId])->save();
            }
        });
    }
}
