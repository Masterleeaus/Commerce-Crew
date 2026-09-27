<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\CommerceCommunicationThreadResource;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationPolicy;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationThread;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceEscalation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class CustomerCommunicationAdminApiController extends Controller
{
    public function threads(Chatbot $chatbot, Request $request)
    {
        $this->assertOwner($chatbot);
        $query = CommerceCommunicationThread::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->orderByDesc('last_message_at');
        foreach (['status', 'priority', 'channel', 'intent'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->input($filter));
            }
        }
        return CommerceCommunicationThreadResource::collection($query->paginate(min(100, max(1, $request->integer('per_page', 25)))));
    }

    public function show(Chatbot $chatbot, CommerceCommunicationThread $thread): CommerceCommunicationThreadResource
    {
        $this->assertOwner($chatbot);
        abort_unless((int) $thread->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        return new CommerceCommunicationThreadResource($thread->load(['messages', 'actions', 'escalations']));
    }

    public function updateThread(Chatbot $chatbot, CommerceCommunicationThread $thread, Request $request): CommerceCommunicationThreadResource
    {
        $this->assertOwner($chatbot);
        abort_unless((int) $thread->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        $validated = $request->validate([
            'identity_verified' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:open,pending,handoff,resolved,closed'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'assigned_to_type' => ['sometimes', 'nullable', 'string', 'max:40'],
            'assigned_to_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'metadata' => ['sometimes', 'array'],
        ]);
        if (($validated['status'] ?? null) === 'resolved') {
            $validated['resolved_at'] = now();
        }
        if (($validated['status'] ?? null) === 'closed') {
            $validated['closed_at'] = now();
        }
        $thread->forceFill($validated)->save();
        return new CommerceCommunicationThreadResource($thread->fresh());
    }

    public function policy(Chatbot $chatbot): JsonResponse
    {
        $this->assertOwner($chatbot);
        $policy = CommerceCommunicationPolicy::query()->firstOrCreate(['chatbot_id' => (int) $chatbot->getAttribute('id')], ['currency' => 'USD']);
        return response()->json(['data' => $policy]);
    }

    public function updatePolicy(Chatbot $chatbot, Request $request): JsonResponse
    {
        $this->assertOwner($chatbot);
        $validated = $request->validate([
            'auto_reply_enabled' => ['sometimes', 'boolean'],
            'auto_reply_confidence' => ['sometimes', 'numeric', 'between:0.5,1'],
            'automatic_refund_limit' => ['sometimes', 'integer', 'min:0'],
            'automatic_discount_limit' => ['sometimes', 'integer', 'min:0'],
            'automatic_store_credit_limit' => ['sometimes', 'integer', 'min:0'],
            'automatic_replacement_limit' => ['sometimes', 'integer', 'min:0'],
            'automatic_cancellation_limit' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'require_identity_for_order_data' => ['sometimes', 'boolean'],
            'human_handoff' => ['sometimes', 'boolean'],
            'return_window_days' => ['sometimes', 'integer', 'between:0,3650'],
            'allowed_auto_actions' => ['sometimes', 'array'],
            'escalation_triggers' => ['sometimes', 'array'],
            'enabled_channels' => ['sometimes', 'array'],
            'specialist_product_ids' => ['sometimes', 'array'],
            'active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);
        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }
        $policy = CommerceCommunicationPolicy::query()->updateOrCreate(['chatbot_id' => (int) $chatbot->getAttribute('id')], $validated);
        return response()->json(['data' => $policy]);
    }

    public function resolveEscalation(Chatbot $chatbot, CommerceEscalation $escalation, Request $request): JsonResponse
    {
        $this->assertOwner($chatbot);
        abort_unless((int) $escalation->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        $validated = $request->validate(['status' => ['required', 'in:acknowledged,resolved'], 'assigned_to_type' => ['sometimes', 'nullable', 'string', 'max:40'], 'assigned_to_id' => ['sometimes', 'nullable', 'string', 'max:191']]);
        $escalation->forceFill([
            'status' => $validated['status'],
            'assigned_to_type' => $validated['assigned_to_type'] ?? $escalation->assigned_to_type,
            'assigned_to_id' => $validated['assigned_to_id'] ?? $escalation->assigned_to_id,
            'acknowledged_at' => $validated['status'] === 'acknowledged' ? now() : $escalation->acknowledged_at,
            'resolved_at' => $validated['status'] === 'resolved' ? now() : $escalation->resolved_at,
        ])->save();
        return response()->json(['data' => $escalation]);
    }

    private function assertOwner(Chatbot $chatbot): void
    {
        abort_unless(Auth::id() !== null && (int) $chatbot->getAttribute('user_id') === (int) Auth::id(), 403);
    }
}
