<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\ConversationContext;
use App\Extensions\ChatbotEcommerce\System\Support\ConversationContextStack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ConversationContextRuntime
{
    public function getOrCreate(int $chatbotId, string $sessionId, array $attributes = []): ConversationContext
    {
        return DB::transaction(function () use ($chatbotId, $sessionId, $attributes): ConversationContext {
            $context = ConversationContext::query()
                ->where('chatbot_id', $chatbotId)
                ->where('session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if (! $context) {
                $context = ConversationContext::query()->create([
                    'chatbot_id' => $chatbotId,
                    'session_id' => $sessionId,
                    'conversation_id' => $attributes['conversation_id'] ?? null,
                    'customer_identity_id' => $attributes['customer_identity_id'] ?? null,
                    'cart_id' => $attributes['cart_id'] ?? null,
                    'current_product_ids' => [],
                    'last_filters' => [],
                    'short_term_preferences' => [],
                    'pending_actions' => [],
                    'context_version' => 1,
                    'expires_at' => now()->addHours((int) config('chatbot-ecommerce.conversation_context.ttl_hours', 24)),
                    'last_accessed_at' => now(),
                ]);
            } else {
                $context->forceFill([
                    'conversation_id' => $attributes['conversation_id'] ?? $context->conversation_id,
                    'customer_identity_id' => $attributes['customer_identity_id'] ?? $context->customer_identity_id,
                    'cart_id' => $attributes['cart_id'] ?? $context->cart_id,
                    'last_accessed_at' => now(),
                    'expires_at' => now()->addHours((int) config('chatbot-ecommerce.conversation_context.ttl_hours', 24)),
                ])->save();
            }

            return $context;
        });
    }

    public function merge(ConversationContext $context, array $changes): ConversationContext
    {
        return DB::transaction(function () use ($context, $changes): ConversationContext {
            $locked = ConversationContext::query()->whereKey($context->id)->lockForUpdate()->firstOrFail();
            $state = ConversationContextStack::merge($this->state($locked), $changes);
            $locked->forceFill(array_intersect_key($state, array_flip([
                'current_product_ids', 'last_query', 'last_filters', 'short_term_preferences', 'pending_actions',
                'selected_product_id', 'selected_variant_id', 'compressed_summary', 'context_version',
            ])) + ['last_accessed_at' => now()])->save();

            return $locked->refresh();
        });
    }

    /** @return array{context:ConversationContext,action:array<string,mixed>,approval_token:string} */
    public function prepareAction(ConversationContext $context, string $type, array $payload, int $ttlMinutes = 10): array
    {
        $token = Str::random(64);
        $action = [
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'payload' => $payload,
            'status' => 'pending_approval',
            'approval_hash' => hash('sha256', $token),
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(max(1, $ttlMinutes))->toIso8601String(),
        ];
        $updated = DB::transaction(function () use ($context, $action): ConversationContext {
            $locked = ConversationContext::query()->whereKey($context->id)->lockForUpdate()->firstOrFail();
            $active = array_values(array_filter((array) $locked->pending_actions, static function (array $existing): bool {
                if (($existing['status'] ?? null) !== 'pending_approval') {
                    return false;
                }
                return ! isset($existing['expires_at']) || now()->lessThan(new \DateTimeImmutable((string) $existing['expires_at']));
            }));
            $active[] = $action;
            $maximum = max(1, (int) config('chatbot-ecommerce.conversation_context.maximum_pending_actions', 20));
            $active = array_slice($active, -$maximum);
            $locked->forceFill([
                'pending_actions' => $active,
                'context_version' => (int) $locked->context_version + 1,
                'last_accessed_at' => now(),
            ])->save();
            return $locked->refresh();
        });

        return ['context' => $updated, 'action' => $action, 'approval_token' => $token];
    }

    /** @return array<string,mixed> */
    public function consumeApprovedAction(ConversationContext $context, string $actionUuid, string $approvalToken): array
    {
        return DB::transaction(function () use ($context, $actionUuid, $approvalToken): array {
            $locked = ConversationContext::query()->whereKey($context->id)->lockForUpdate()->firstOrFail();
            $actions = array_values((array) $locked->pending_actions);
            $matched = null;
            foreach ($actions as $index => $action) {
                if (($action['uuid'] ?? null) !== $actionUuid) {
                    continue;
                }
                if (($action['status'] ?? null) !== 'pending_approval') {
                    throw ValidationException::withMessages(['action' => 'The action is no longer awaiting approval.']);
                }
                if (isset($action['expires_at']) && now()->greaterThan(new \DateTimeImmutable((string) $action['expires_at']))) {
                    $actions[$index]['status'] = 'expired';
                    $locked->forceFill(['pending_actions' => $actions])->save();
                    throw ValidationException::withMessages(['action' => 'The action approval has expired.']);
                }
                if (! hash_equals((string) ($action['approval_hash'] ?? ''), hash('sha256', $approvalToken))) {
                    throw ValidationException::withMessages(['approval_token' => 'The action approval token is invalid.']);
                }
                $actions[$index]['status'] = 'approved';
                $actions[$index]['approved_at'] = now()->toIso8601String();
                $matched = $actions[$index];
                break;
            }
            if ($matched === null) {
                throw ValidationException::withMessages(['action' => 'The pending action could not be found.']);
            }
            $locked->forceFill(['pending_actions' => $actions, 'context_version' => (int) $locked->context_version + 1])->save();

            return $matched;
        });
    }

    /** @return array<string,mixed> */
    public function state(ConversationContext $context): array
    {
        return [
            'current_product_ids' => array_values((array) $context->current_product_ids),
            'last_query' => $context->last_query,
            'last_filters' => (array) $context->last_filters,
            'short_term_preferences' => (array) $context->short_term_preferences,
            'pending_actions' => (array) $context->pending_actions,
            'selected_product_id' => $context->selected_product_id ? (int) $context->selected_product_id : null,
            'selected_variant_id' => $context->selected_variant_id ? (int) $context->selected_variant_id : null,
            'compressed_summary' => $context->compressed_summary,
            'context_version' => (int) $context->context_version,
        ];
    }
}
