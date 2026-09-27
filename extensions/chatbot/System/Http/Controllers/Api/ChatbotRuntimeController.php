<?php

namespace App\Extensions\Chatbot\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\ChatbotAttachment;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;
use App\Extensions\Chatbot\System\Models\ChatbotParticipant;
use App\Extensions\Chatbot\System\Services\AttachmentRuntime;
use App\Extensions\Chatbot\System\Services\ConversationRuntime;
use App\Extensions\Chatbot\System\Services\DraftRuntime;
use App\Extensions\Chatbot\System\Services\HealthCheckService;
use App\Extensions\Chatbot\System\Services\MessageRuntime;
use App\Extensions\Chatbot\System\Services\ParticipantRuntime;
use App\Extensions\Chatbot\System\Services\PresenceRuntime;
use App\Extensions\Chatbot\System\Services\StreamingRuntime;
use App\Extensions\Chatbot\System\Services\StructuredActionRuntime;
use App\Extensions\Chatbot\System\Services\WorkflowRuntime;
use App\Extensions\Chatbot\System\Models\ChatbotStructuredAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatbotRuntimeController extends Controller
{
    public function health(HealthCheckService $health): JsonResponse
    {
        return response()->json($health->check());
    }

    public function search(Request $request, ConversationRuntime $runtime): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:500'],
            'channel' => ['nullable', 'string', 'max:64'],
            'archived' => ['nullable', 'boolean'],
            'chatbot_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($runtime->search($filters));
    }

    public function archive(ChatbotConversation $conversation, ConversationRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);

        return response()->json($runtime->archive($conversation));
    }

    public function merge(Request $request, ChatbotConversation $conversation, ConversationRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate(['source_id' => ['required', 'integer', 'exists:ext_chatbot_conversations,id']]);
        $source = ChatbotConversation::query()->findOrFail($data['source_id']);
        $this->authorize('update', $source->chatbot);

        return response()->json($runtime->merge($conversation, $source));
    }

    public function summarize(Request $request, ChatbotConversation $conversation, ConversationRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate(['force' => ['nullable', 'boolean']]);

        return response()->json(['summary' => $runtime->summarize($conversation, (bool) ($data['force'] ?? false))]);
    }


    public function createMessage(Request $request, ChatbotConversation $conversation, MessageRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate([
            'message' => ['required', 'string'],
            'role' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'max:64'],
            'message_type' => ['nullable', 'string', 'max:64'],
            'content_type' => ['nullable', 'string', 'max:64'],
            'media_url' => ['nullable', 'string', 'max:2048'],
            'media_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ]);
        $data['idempotency_key'] ??= $request->header('Idempotency-Key');

        return response()->json($runtime->create($conversation, $data), 201);
    }

    public function storeAttachment(Request $request, ChatbotConversation $conversation, AttachmentRuntime $attachments): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate([
            'file' => ['required', 'file'],
            'history_id' => ['nullable', 'integer', 'exists:ext_chatbot_histories,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (! empty($data['history_id'])) {
            $belongs = ChatbotHistory::query()
                ->whereKey($data['history_id'])
                ->where('conversation_id', $conversation->id)
                ->exists();
            abort_unless($belongs, 422, 'The selected message does not belong to this conversation.');
        }

        $attachment = $attachments->store($data['file'], [
            'conversation_id' => $conversation->id,
            'history_id' => $data['history_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);

        return response()->json($attachment, 201);
    }

    public function downloadAttachment(ChatbotAttachment $attachment, AttachmentRuntime $attachments): StreamedResponse
    {
        abort_unless($attachment->conversation, 404);
        $this->authorize('view', $attachment->conversation->chatbot);

        return $attachments->download($attachment);
    }

    public function deleteAttachment(ChatbotAttachment $attachment, AttachmentRuntime $attachments): JsonResponse
    {
        abort_unless($attachment->conversation, 404);
        $this->authorize('update', $attachment->conversation->chatbot);
        $attachments->delete($attachment);

        return response()->json(status: 204);
    }

    public function sent(Request $request, ChatbotHistory $message, MessageRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $message->conversation->chatbot);
        $data = $request->validate(['provider_message_id' => ['nullable', 'string', 'max:191']]);

        return response()->json($runtime->markSent($message, $data['provider_message_id'] ?? null));
    }

    public function delivered(Request $request, ChatbotHistory $message, MessageRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $message->conversation->chatbot);
        $data = $request->validate(['provider_message_id' => ['nullable', 'string', 'max:191']]);

        return response()->json($runtime->markDelivered($message, $data['provider_message_id'] ?? null));
    }

    public function failed(Request $request, ChatbotHistory $message, MessageRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $message->conversation->chatbot);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:4000'],
            'payload' => ['nullable', 'array'],
        ]);

        return response()->json($runtime->markFailed($message, $data['reason'], $data['payload'] ?? []));
    }

    public function retry(ChatbotHistory $message, MessageRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $message->conversation->chatbot);

        return response()->json($runtime->retry($message));
    }

    public function read(ChatbotHistory $message, MessageRuntime $runtime): JsonResponse
    {
        $this->authorize('update', $message->conversation->chatbot);

        return response()->json($runtime->markRead($message));
    }

    public function streamEvents(Request $request, ChatbotConversation $conversation, StreamingRuntime $stream): JsonResponse
    {
        $this->authorize('view', $conversation->chatbot);
        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'event' => ['nullable', 'string', 'max:191'],
            'limit' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($stream->replay(
            $conversation,
            isset($data['after_id']) ? (int) $data['after_id'] : null,
            $data['event'] ?? null,
            (int) ($data['limit'] ?? 100),
        ));
    }

    public function typing(Request $request, ChatbotConversation $conversation, StreamingRuntime $stream): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate([
            'participant' => ['required', 'string', 'max:191'],
            'active' => ['required', 'boolean'],
        ]);
        $stream->typing($conversation->id, $data['participant'], $data['active']);

        return response()->json(['ok' => true]);
    }

    public function presence(ChatbotConversation $conversation, PresenceRuntime $presence): JsonResponse
    {
        $this->authorize('view', $conversation->chatbot);

        return response()->json(['data' => $presence->active($conversation)]);
    }

    public function touchPresence(Request $request, ChatbotConversation $conversation, PresenceRuntime $presence): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate([
            'participant_key' => ['required', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'in:online,away,busy,offline'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json($presence->touch(
            $conversation,
            $data['participant_key'],
            $data['state'] ?? 'online',
            $data['metadata'] ?? [],
        ));
    }


    public function participants(ChatbotConversation $conversation, ParticipantRuntime $participants): JsonResponse
    {
        $this->authorize('view', $conversation->chatbot);

        return response()->json(['data' => $participants->list($conversation)]);
    }

    public function saveParticipant(Request $request, ChatbotConversation $conversation, ParticipantRuntime $participants): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'reference' => ['nullable', 'string', 'max:191'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json($participants->upsert($conversation, $data), 201);
    }

    public function markParticipantRead(Request $request, ChatbotConversation $conversation, ChatbotParticipant $participant, ParticipantRuntime $participants): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate(['history_id' => ['nullable', 'integer']]);
        $participant = $participants->markRead($conversation, $participant, $data['history_id'] ?? null);

        return response()->json([
            'data' => $participant,
            'unread_count' => $participants->unreadCount($conversation, $participant),
        ]);
    }

    public function deleteParticipant(ChatbotConversation $conversation, ChatbotParticipant $participant, ParticipantRuntime $participants): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $participants->remove($conversation, $participant);

        return response()->json(status: 204);
    }

    public function actions(Request $request, ChatbotConversation $conversation, StructuredActionRuntime $actions): JsonResponse
    {
        $this->authorize('view', $conversation->chatbot);
        $data = $request->validate(['status' => ['nullable', 'string', 'in:pending,approved,rejected,executed,cancelled,expired']]);
        return response()->json(['data' => $actions->list($conversation, $data['status'] ?? null)]);
    }

    public function createAction(Request $request, ChatbotConversation $conversation, StructuredActionRuntime $actions): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate([
            'history_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $data['idempotency_key'] ??= $request->header('Idempotency-Key');
        return response()->json($actions->create($conversation, $data), 201);
    }

    public function approveAction(Request $request, ChatbotConversation $conversation, ChatbotStructuredAction $action, StructuredActionRuntime $actions): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        return response()->json($actions->approve($conversation, $action, $request->user()?->id));
    }

    public function rejectAction(Request $request, ChatbotConversation $conversation, ChatbotStructuredAction $action, StructuredActionRuntime $actions): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:4000']]);
        return response()->json($actions->reject($conversation, $action, $request->user()?->id, $data['reason'] ?? null));
    }

    public function executeAction(Request $request, ChatbotConversation $conversation, ChatbotStructuredAction $action, StructuredActionRuntime $actions): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate(['result' => ['nullable', 'array']]);
        return response()->json($actions->execute($conversation, $action, $data['result'] ?? []));
    }

    public function cancelAction(ChatbotConversation $conversation, ChatbotStructuredAction $action, StructuredActionRuntime $actions): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        return response()->json($actions->cancel($conversation, $action));
    }


    public function dispatchWorkflow(Request $request, ChatbotConversation $conversation, WorkflowRuntime $workflows): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate([
            'workflow' => ['required', 'string', 'max:191'],
            'context' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);
        $data['idempotency_key'] ??= $request->header('Idempotency-Key');

        return response()->json($workflows->dispatch($data['workflow'], $data['context'] ?? [], [
            'conversation_id' => $conversation->id,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'max_attempts' => $data['max_attempts'] ?? 3,
        ]), 201);
    }

    public function workflow(ChatbotConversation $conversation, string $runId, WorkflowRuntime $workflows): JsonResponse
    {
        $this->authorize('view', $conversation->chatbot);
        $run = $workflows->get($runId);
        abort_unless((int) ($run['conversation_id'] ?? 0) === (int) $conversation->id, 404);

        return response()->json(['data' => $run]);
    }

    public function resumeWorkflow(Request $request, ChatbotConversation $conversation, string $runId, WorkflowRuntime $workflows): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $this->assertWorkflowConversation($workflows, $runId, $conversation);
        $data = $request->validate(['input' => ['nullable', 'array']]);

        return response()->json($workflows->resume($runId, $data['input'] ?? []));
    }

    public function completeWorkflow(Request $request, ChatbotConversation $conversation, string $runId, WorkflowRuntime $workflows): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $this->assertWorkflowConversation($workflows, $runId, $conversation);
        $data = $request->validate(['output' => ['nullable', 'array']]);

        return response()->json($workflows->complete($runId, $data['output'] ?? []));
    }

    public function failWorkflow(Request $request, ChatbotConversation $conversation, string $runId, WorkflowRuntime $workflows): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $this->assertWorkflowConversation($workflows, $runId, $conversation);
        $data = $request->validate([
            'error' => ['required', 'string', 'max:8000'],
            'output' => ['nullable', 'array'],
        ]);

        return response()->json($workflows->fail($runId, $data['error'], $data['output'] ?? []));
    }

    public function retryWorkflow(Request $request, ChatbotConversation $conversation, string $runId, WorkflowRuntime $workflows): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $this->assertWorkflowConversation($workflows, $runId, $conversation);
        $data = $request->validate(['delay_seconds' => ['nullable', 'integer', 'min:0', 'max:86400']]);

        return response()->json($workflows->retry($runId, $data['delay_seconds'] ?? null));
    }

    public function cancelWorkflow(ChatbotConversation $conversation, string $runId, WorkflowRuntime $workflows): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $this->assertWorkflowConversation($workflows, $runId, $conversation);
        $workflows->cancel($runId);

        return response()->json(status: 204);
    }

    private function assertWorkflowConversation(WorkflowRuntime $workflows, string $runId, ChatbotConversation $conversation): void
    {
        $run = $workflows->get($runId);
        abort_unless((int) ($run['conversation_id'] ?? 0) === (int) $conversation->id, 404);
    }

    public function draft(Request $request, ChatbotConversation $conversation, DraftRuntime $drafts): JsonResponse
    {
        $this->authorize('view', $conversation->chatbot);

        return response()->json(['data' => $drafts->get($conversation, $request->user()?->id)]);
    }

    public function saveDraft(Request $request, ChatbotConversation $conversation, DraftRuntime $drafts): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $data = $request->validate([
            'content' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json($drafts->save($conversation, $request->user()?->id, $data));
    }

    public function deleteDraft(Request $request, ChatbotConversation $conversation, DraftRuntime $drafts): JsonResponse
    {
        $this->authorize('update', $conversation->chatbot);
        $drafts->delete($conversation, $request->user()?->id);

        return response()->json(status: 204);
    }
}
