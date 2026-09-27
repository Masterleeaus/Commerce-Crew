<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceActionJournal;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceSpendLimit;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceActionJournalRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\ConversationContextRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\ConversationalCommerceRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ConversationalCommerceApiController extends Controller
{
    public function definitions(ConversationalCommerceRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $runtime->toolDefinitions()]);
    }

    public function context(Chatbot $chatbot, string $sessionId, Request $request, ConversationContextRuntime $runtime): JsonResponse
    {
        $context = $runtime->getOrCreate((int) $chatbot->getAttribute('id'), $sessionId, $request->only(['conversation_id', 'customer_identity_id']));
        return response()->json(['data' => $runtime->state($context)]);
    }

    public function updateContext(Chatbot $chatbot, string $sessionId, Request $request, ConversationContextRuntime $runtime): JsonResponse
    {
        $validated = $request->validate([
            'current_product_ids' => ['sometimes', 'array', 'max:50'],
            'current_product_ids.*' => ['integer', 'min:1'],
            'last_query' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'last_filters' => ['sometimes', 'array'],
            'short_term_preferences' => ['sometimes', 'array'],
            'selected_product_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'selected_variant_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'compressed_summary' => ['sometimes', 'nullable', 'string', 'max:8000'],
        ]);
        $context = $runtime->getOrCreate((int) $chatbot->getAttribute('id'), $sessionId, $request->only(['conversation_id', 'customer_identity_id']));
        return response()->json(['data' => $runtime->state($runtime->merge($context, $validated))]);
    }

    public function execute(Chatbot $chatbot, string $sessionId, Request $request, ConversationalCommerceRuntime $runtime): JsonResponse
    {
        $validated = $request->validate([
            'tool' => ['required', 'string', 'max:120'],
            'arguments' => ['sometimes', 'array'],
        ]);
        if ($validated['tool'] === 'native_execute_action') {
            abort(422, 'Approved actions must be executed through the dedicated action endpoint.');
        }
        $arguments = (array) ($validated['arguments'] ?? []);
        $arguments['conversation_id'] ??= $request->integer('conversation_id') ?: null;
        $arguments['customer_identity_id'] ??= $request->integer('customer_identity_id') ?: null;

        return response()->json($runtime->execute($chatbot, $sessionId, $validated['tool'], $arguments));
    }

    public function executeApproved(Chatbot $chatbot, string $sessionId, string $actionUuid, Request $request, ConversationalCommerceRuntime $runtime): JsonResponse
    {
        $validated = $request->validate([
            'approval_token' => ['required', 'string', 'min:32', 'max:255'],
            'conversation_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'customer_identity_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        return response()->json($runtime->execute($chatbot, $sessionId, 'native_execute_action', [
            'action_uuid' => $actionUuid,
            'approval_token' => $validated['approval_token'],
            'conversation_id' => $validated['conversation_id'] ?? null,
            'customer_identity_id' => $validated['customer_identity_id'] ?? null,
        ]));
    }

    public function putBudget(Chatbot $chatbot, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_identity_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'currency' => ['required', 'string', 'size:3'],
            'max_order_value' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'daily_spend_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'enabled' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $limit = CommerceSpendLimit::query()->firstOrNew([
            'chatbot_id' => (int) $chatbot->getAttribute('id'),
            'customer_identity_id' => $validated['customer_identity_id'] ?? null,
            'session_id' => $validated['session_id'] ?? null,
        ]);
        $limit->uuid ??= (string) Str::uuid();
        $limit->forceFill([
            'currency' => strtoupper($validated['currency']),
            'max_order_value' => $validated['max_order_value'] ?? null,
            'daily_spend_limit' => $validated['daily_spend_limit'] ?? null,
            'enabled' => (bool) ($validated['enabled'] ?? true),
            'metadata' => $validated['metadata'] ?? [],
        ])->save();

        return response()->json(['data' => $limit]);
    }

    public function rollback(Chatbot $chatbot, CommerceActionJournal $journal, CommerceActionJournalRuntime $runtime): JsonResponse
    {
        abort_unless((int) $journal->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        return response()->json(['data' => $runtime->rollback($journal)]);
    }
}
