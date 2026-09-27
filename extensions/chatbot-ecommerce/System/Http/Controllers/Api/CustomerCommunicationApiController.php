<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\CommerceCommunicationThreadResource;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationMessage;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationThread;
use App\Extensions\ChatbotEcommerce\System\Services\CustomerCommunicationRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerCommunicationApiController extends Controller
{
    public function tools(CustomerCommunicationRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $runtime->toolDefinitions()]);
    }

    public function ingest(Chatbot $chatbot, string $sessionId, Request $request, CustomerCommunicationRuntime $runtime): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'in:web,email,sms,whatsapp,telegram,messenger,instagram,marketplace_message,internal_inbox'],
            'external_thread_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'external_message_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'conversation_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'customer_identity_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actor_type' => ['sometimes', 'in:customer,seller,agent,system'],
            'actor_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'direction' => ['sometimes', 'in:inbound,outbound'],
            'message_type' => ['sometimes', 'in:text,image,audio,file,system'],
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:20000'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'intent' => ['sometimes', 'nullable', 'string', 'max:100'],
            'confidence' => ['sometimes', 'numeric', 'between:0,1'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:80'],
            'provider_message_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $validated['session_id'] = $sessionId;
        $validated['inbound_support'] = true;
        $validated['identity_verified'] = false;

        return response()->json(['data' => $runtime->ingestMessage($chatbot, $validated)], 201);
    }

    public function context(Chatbot $chatbot, string $sessionId, CommerceCommunicationThread $thread, CustomerCommunicationRuntime $runtime): JsonResponse
    {
        $this->assertSession($thread, $sessionId);
        return response()->json(['data' => $runtime->threadContext($chatbot, $thread)]);
    }

    public function draft(Chatbot $chatbot, string $sessionId, CommerceCommunicationThread $thread, Request $request, CustomerCommunicationRuntime $runtime): JsonResponse
    {
        $this->assertSession($thread, $sessionId);
        $validated = $request->validate(['message_uuid' => ['sometimes', 'nullable', 'string', 'max:36']]);
        $message = isset($validated['message_uuid'])
            ? CommerceCommunicationMessage::query()->where('thread_id', $thread->id)->where('uuid', $validated['message_uuid'])->firstOrFail()
            : null;
        return response()->json(['data' => $runtime->draftReply($chatbot, $thread, $message)]);
    }

    public function prepareAction(Chatbot $chatbot, string $sessionId, CommerceCommunicationThread $thread, Request $request, CustomerCommunicationRuntime $runtime): JsonResponse
    {
        $this->assertSession($thread, $sessionId);
        $validated = $request->validate([
            'message_uuid' => ['sometimes', 'nullable', 'string', 'max:36'],
            'action_type' => ['required', 'in:prepare_return,request_return,cancel_order,issue_refund,correct_address,offer_discount,replace_item,issue_store_credit'],
            'payload' => ['required', 'array'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $message = isset($validated['message_uuid'])
            ? CommerceCommunicationMessage::query()->where('thread_id', $thread->id)->where('uuid', $validated['message_uuid'])->firstOrFail()
            : null;
        $result = $runtime->prepareAction($chatbot, $thread, $message, $validated['action_type'], $validated['payload'], $validated['idempotency_key']);
        return response()->json(['data' => $result], 201);
    }

    public function executeAction(Chatbot $chatbot, string $sessionId, CommerceCommunicationThread $thread, string $actionUuid, Request $request, CustomerCommunicationRuntime $runtime): JsonResponse
    {
        $this->assertSession($thread, $sessionId);
        $validated = $request->validate([
            'approval_token' => ['sometimes', 'nullable', 'string', 'size:64'],
            'actor_type' => ['required', 'in:customer,seller,agent,system'],
            'actor_id' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        return response()->json(['data' => $runtime->executeApprovedAction($chatbot, $thread, $actionUuid, $validated['approval_token'] ?? null, $validated['actor_type'], $validated['actor_id'] ?? null)]);
    }

    public function escalate(Chatbot $chatbot, string $sessionId, CommerceCommunicationThread $thread, Request $request, CustomerCommunicationRuntime $runtime): JsonResponse
    {
        $this->assertSession($thread, $sessionId);
        $validated = $request->validate([
            'message_uuid' => ['sometimes', 'nullable', 'string', 'max:36'],
            'reason' => ['required', 'string', 'max:100'],
            'severity' => ['sometimes', 'in:low,normal,high,critical'],
            'summary' => ['required', 'string', 'max:4000'],
            'recommended_action' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);
        $message = isset($validated['message_uuid'])
            ? CommerceCommunicationMessage::query()->where('thread_id', $thread->id)->where('uuid', $validated['message_uuid'])->firstOrFail()
            : null;
        $result = $runtime->handoff($chatbot, $thread, $message, $validated['reason'], $validated['severity'] ?? 'normal', $validated['summary'], $validated['recommended_action'] ?? null);
        return response()->json(['data' => $result], 201);
    }

    private function assertSession(CommerceCommunicationThread $thread, string $sessionId): void
    {
        abort_unless((string) $thread->session_id === $sessionId, 404);
    }
}
