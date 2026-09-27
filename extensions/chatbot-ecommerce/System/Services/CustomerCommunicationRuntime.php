<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationAction;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationMessage;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationPolicy;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationThread;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceEscalation;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrderEvent;
use App\Extensions\ChatbotEcommerce\System\Support\CommerceRole;
use App\Extensions\ChatbotEcommerce\System\Support\CustomerCommunicationAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CustomerCommunicationRuntime
{
    public function __construct(
        private readonly CommerceRoleRuntime $roles,
        private readonly CustomerCommunicationContextRuntime $contexts,
        private readonly CustomerCommunicationCardRuntime $cards,
        private readonly NativeOrderRuntime $orders,
        private readonly PaymentRuntime $payments,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function toolDefinitions(): array
    {
        return [
            $this->definition('support_get_customer_context', 'inform', 'Retrieve verified cart, order, payment, fulfilment, and return context for a support thread.', ['thread_uuid' => ['type' => 'string']], ['thread_uuid']),
            $this->definition('support_draft_reply', 'inform', 'Prepare a fact-grounded reply for a customer message without sending it.', ['thread_uuid' => ['type' => 'string'], 'message_uuid' => ['type' => 'string']], ['thread_uuid']),
            $this->definition('support_prepare_action', 'prepare', 'Prepare a governed support action and evaluate automatic limits or approval requirements.', [
                'thread_uuid' => ['type' => 'string'], 'action_type' => ['type' => 'string'], 'payload' => ['type' => 'object'], 'idempotency_key' => ['type' => 'string'],
            ], ['thread_uuid', 'action_type', 'payload', 'idempotency_key']),
            $this->definition('support_handoff_to_human', 'human_only', 'Escalate the conversation with a factual summary and recommended next action.', [
                'thread_uuid' => ['type' => 'string'], 'reason' => ['type' => 'string'], 'severity' => ['type' => 'string'], 'summary' => ['type' => 'string'],
            ], ['thread_uuid', 'reason', 'summary']),
        ];
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function ingestMessage(Chatbot $chatbot, array $data): array
    {
        $role = $this->roles->resolve([
            'actor_type' => (string) ($data['actor_type'] ?? 'customer'),
            'requested_role' => CommerceRole::CUSTOMER_COMMUNICATIONS,
            'channel' => (string) ($data['channel'] ?? 'web'),
            'intent' => (string) ($data['intent'] ?? ''),
            'inbound_support' => true,
            'authenticated' => (bool) ($data['authenticated'] ?? false),
        ]);
        if ($role !== CommerceRole::CUSTOMER_COMMUNICATIONS) {
            throw ValidationException::withMessages(['role' => 'This message is not authorised for the customer communications role.']);
        }

        $chatbotId = (int) $chatbot->getAttribute('id');
        $channel = strtolower(trim((string) ($data['channel'] ?? 'web')));
        $externalMessageId = trim((string) ($data['external_message_id'] ?? ''));

        if ($externalMessageId !== '') {
            $existing = CommerceCommunicationMessage::query()
                ->where('chatbot_id', $chatbotId)
                ->where('channel', $channel)
                ->where('external_message_id', $externalMessageId)
                ->first();
            if ($existing !== null) {
                return ['duplicate' => true, 'message' => $existing->toArray(), 'thread' => $existing->thread()->first()?->toArray()];
            }
        }

        $result = DB::transaction(function () use ($chatbot, $data, $chatbotId, $channel, $externalMessageId): array {
            $thread = $this->findOrCreateThread($chatbot, $data, $channel);
            $body = trim((string) ($data['body'] ?? ''));
            $intent = trim((string) ($data['intent'] ?? '')) ?: $this->classifyIntent($body);
            $confidence = max(0.0, min(1.0, (float) ($data['confidence'] ?? $this->defaultConfidence($intent))));

            $message = CommerceCommunicationMessage::query()->create([
                'thread_id' => $thread->id,
                'chatbot_id' => $chatbotId,
                'channel' => $channel,
                'external_message_id' => $externalMessageId !== '' ? $externalMessageId : null,
                'direction' => (string) ($data['direction'] ?? 'inbound'),
                'actor_type' => (string) ($data['actor_type'] ?? 'customer'),
                'actor_id' => isset($data['actor_id']) ? (string) $data['actor_id'] : null,
                'role' => CommerceRole::CUSTOMER_COMMUNICATIONS,
                'message_type' => (string) ($data['message_type'] ?? 'text'),
                'body' => $body,
                'safe_summary' => mb_substr($body, 0, 500),
                'intent' => $intent,
                'confidence' => $confidence,
                'provider' => $data['provider'] ?? null,
                'provider_message_id' => $data['provider_message_id'] ?? null,
                'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
                'attachments' => (array) ($data['attachments'] ?? []),
                'metadata' => (array) ($data['metadata'] ?? []),
                'received_at' => now(),
            ]);

            $thread->forceFill([
                'intent' => $intent,
                'confidence' => $confidence,
                'priority' => $this->priorityFor($intent, $body),
                'sentiment' => $this->sentimentFor($body),
                'last_message_at' => now(),
                'status' => $thread->status === 'closed' ? 'open' : $thread->status,
            ])->save();

            $decision = CustomerCommunicationAuthority::decision($this->authorityActionForIntent($intent), [
                'confidence' => $confidence,
                'identity_verified' => (bool) $thread->identity_verified,
            ]);

            return ['duplicate' => false, 'thread' => $thread->fresh()->toArray(), 'message' => $message->toArray(), 'authority' => $decision];
        });

        if (($result['authority']['escalate'] ?? false) && (bool) config('chatbot-ecommerce.customer_communications.human_handoff', true)) {
            $thread = CommerceCommunicationThread::query()->where('uuid', (string) $result['thread']['uuid'])->firstOrFail();
            $message = CommerceCommunicationMessage::query()->where('uuid', (string) $result['message']['uuid'])->firstOrFail();
            $result['handoff'] = $this->handoff(
                $chatbot,
                $thread,
                $message,
                (string) ($thread->intent ?: 'human_review'),
                'high',
                'The customer message triggered a human-only commerce support rule.',
                'Review the conversation and respond through the connected channel inbox.',
            );
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function threadContext(Chatbot $chatbot, CommerceCommunicationThread $thread): array
    {
        $this->assertThreadScope($chatbot, $thread);

        return $this->contexts->build($chatbot, $thread);
    }

    /** @return array<string,mixed> */
    public function draftReply(Chatbot $chatbot, CommerceCommunicationThread $thread, ?CommerceCommunicationMessage $message = null): array
    {
        $this->assertThreadScope($chatbot, $thread);
        $message ??= $thread->messages()->where('direction', 'inbound')->orderByDesc('id')->first();
        $intent = (string) ($message?->intent ?: $thread->intent ?: 'general_support');
        $confidence = (float) ($message?->confidence ?: $thread->confidence ?: 0.5);
        $context = $this->threadContext($chatbot, $thread);
        $policy = $this->policyFor((int) $chatbot->getAttribute('id'));
        $decision = CustomerCommunicationAuthority::decision($this->authorityActionForIntent($intent), [
            'confidence' => $confidence,
            'identity_verified' => (bool) $thread->identity_verified,
            ...$this->policyLimits($policy),
        ]);

        $text = $this->replyText($intent, $context, (bool) $thread->identity_verified);
        $draft = [
            'text' => $text,
            'intent' => $intent,
            'confidence' => $confidence,
            'authority' => $decision,
            'can_auto_send' => (bool) $policy->auto_reply_enabled
                && $decision['level'] === 'answer_automatically'
                && $decision['allowed']
                && $confidence >= (float) $policy->auto_reply_confidence,
            'facts' => $context,
            'actions' => $this->suggestedActions($intent, $context),
            'constraints' => [
                'do_not_claim_an_action_completed_without_a_persisted_result' => true,
                'do_not_reveal_private_order_data_without_verified_identity' => true,
                'do_not_invent_tracking_payment_or_refund_state' => true,
            ],
        ];

        return ['draft' => $draft, 'ui' => $this->cards->reply($thread, $draft)];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function prepareAction(Chatbot $chatbot, CommerceCommunicationThread $thread, ?CommerceCommunicationMessage $message, string $actionType, array $payload, string $idempotencyKey): array
    {
        $this->assertThreadScope($chatbot, $thread);
        $chatbotId = (int) $chatbot->getAttribute('id');
        $existing = CommerceCommunicationAction::query()->where('chatbot_id', $chatbotId)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return ['action' => $existing->toArray(), 'approval_token' => null, 'idempotent_replay' => true];
        }

        $policy = $this->policyFor($chatbotId);
        $decision = CustomerCommunicationAuthority::decision($actionType, [
            'confidence' => (float) ($message?->confidence ?: $thread->confidence ?: 0.0),
            'identity_verified' => (bool) $thread->identity_verified,
            'amount' => (int) ($payload['amount'] ?? 0),
            ...$this->policyLimits($policy),
        ]);

        if ($decision['level'] === 'human_only') {
            $escalation = $this->handoff($chatbot, $thread, $message, $actionType, 'high', $decision['reason'], 'A human should review the requested action.');
            return ['authority' => $decision, 'escalation' => $escalation, 'action' => null, 'approval_token' => null];
        }

        if (in_array($actionType, ['offer_discount', 'replace_item', 'issue_store_credit'], true)) {
            $escalation = $this->handoff(
                $chatbot,
                $thread,
                $message,
                'connected_system_required',
                'normal',
                'The requested support action requires a connected discount, replacement, or credit provider that is not owned by this extension.',
                'Review and execute the proposed action in the connected seller system.',
            );
            return ['authority' => $decision, 'escalation' => $escalation, 'action' => null, 'approval_token' => null];
        }

        $approvalToken = null;
        $status = $decision['level'] === 'execute_within_limits' ? 'ready' : 'awaiting_approval';
        if ($status === 'awaiting_approval') {
            $approvalToken = bin2hex(random_bytes(32));
        }

        $action = CommerceCommunicationAction::query()->create([
            'thread_id' => $thread->id,
            'message_id' => $message?->id,
            'chatbot_id' => $chatbotId,
            'action_type' => $actionType,
            'authority_level' => $decision['level'],
            'status' => $status,
            'amount' => isset($payload['amount']) ? (int) $payload['amount'] : null,
            'currency' => isset($payload['currency']) ? strtoupper((string) $payload['currency']) : $policy->currency,
            'idempotency_key' => $idempotencyKey,
            'approval_token_hash' => $approvalToken !== null ? hash('sha256', $approvalToken) : null,
            'payload' => $payload,
            'expires_at' => now()->addMinutes((int) config('chatbot-ecommerce.customer_communications.action_ttl_minutes', 15)),
        ]);

        return ['authority' => $decision, 'action' => $action->toArray(), 'approval_token' => $approvalToken, 'idempotent_replay' => false];
    }

    /** @return array<string,mixed> */
    public function executeApprovedAction(Chatbot $chatbot, CommerceCommunicationThread $thread, string $actionUuid, ?string $approvalToken, string $actorType, string|int|null $actorId): array
    {
        $this->assertThreadScope($chatbot, $thread);
        $action = CommerceCommunicationAction::query()
            ->where('thread_id', $thread->id)
            ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
            ->where('uuid', $actionUuid)
            ->lockForUpdate()
            ->firstOrFail();

        if ($action->status === 'executed') {
            return ['action' => $action->toArray(), 'result' => $action->result, 'idempotent_replay' => true];
        }
        if ($action->expires_at !== null && $action->expires_at->isPast()) {
            $action->forceFill(['status' => 'expired'])->save();
            throw ValidationException::withMessages(['action' => 'The customer-service action has expired.']);
        }
        if ($action->status === 'awaiting_approval') {
            if ($approvalToken === null || ! hash_equals((string) $action->approval_token_hash, hash('sha256', $approvalToken))) {
                throw ValidationException::withMessages(['approval_token' => 'The approval token is invalid.']);
            }
            $action->forceFill([
                'status' => 'approved', 'approved_by_type' => $actorType,
                'approved_by_id' => $actorId !== null ? (string) $actorId : null, 'approved_at' => now(),
            ])->save();
        } elseif (! in_array($action->status, ['ready', 'approved'], true)) {
            throw ValidationException::withMessages(['action' => 'The customer-service action cannot be executed from its current state.']);
        }

        try {
            $result = DB::transaction(fn (): array => $this->performAction($chatbot, $thread, $action));
            $action->forceFill([
                'status' => 'executed', 'result' => $result,
                'executed_by_type' => $actorType, 'executed_by_id' => $actorId !== null ? (string) $actorId : null,
                'executed_at' => now(), 'failure_reason' => null,
            ])->save();

            return ['action' => $action->fresh()->toArray(), 'result' => $result, 'idempotent_replay' => false];
        } catch (Throwable $exception) {
            $action->forceFill(['status' => 'failed', 'failure_reason' => mb_substr($exception->getMessage(), 0, 1000)])->save();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function handoff(Chatbot $chatbot, CommerceCommunicationThread $thread, ?CommerceCommunicationMessage $message, string $reason, string $severity, string $summary, ?string $recommendedAction = null): array
    {
        $this->assertThreadScope($chatbot, $thread);
        $context = $this->threadContext($chatbot, $thread);
        $escalation = CommerceEscalation::query()->create([
            'thread_id' => $thread->id,
            'message_id' => $message?->id,
            'chatbot_id' => (int) $chatbot->getAttribute('id'),
            'reason' => $reason,
            'severity' => $severity,
            'status' => 'open',
            'summary' => $summary,
            'recommended_action' => $recommendedAction,
            'context_snapshot' => $context,
            'metadata' => ['source' => 'chatbot-ecommerce'],
        ]);
        $thread->forceFill(['status' => 'handoff', 'priority' => in_array($severity, ['high', 'critical'], true) ? 'urgent' : $thread->priority])->save();

        return ['escalation' => $escalation->toArray(), 'ui' => $this->cards->escalation($escalation)];
    }

    /** @param array<string,mixed> $data */
    private function findOrCreateThread(Chatbot $chatbot, array $data, string $channel): CommerceCommunicationThread
    {
        $chatbotId = (int) $chatbot->getAttribute('id');
        $externalThreadId = trim((string) ($data['external_thread_id'] ?? ''));
        $query = CommerceCommunicationThread::query()->where('chatbot_id', $chatbotId)->where('channel', $channel);
        if ($externalThreadId !== '') {
            $thread = $query->where('external_thread_id', $externalThreadId)->first();
            if ($thread !== null) {
                return $thread;
            }
        }

        return CommerceCommunicationThread::query()->create([
            'chatbot_id' => $chatbotId,
            'owner_user_id' => $chatbot->getAttribute('user_id'),
            'customer_identity_id' => $data['customer_identity_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'role' => CommerceRole::CUSTOMER_COMMUNICATIONS,
            'channel' => $channel,
            'external_thread_id' => $externalThreadId !== '' ? $externalThreadId : null,
            'subject' => $data['subject'] ?? null,
            'status' => 'open',
            'priority' => 'normal',
            'identity_verified' => (bool) ($data['identity_verified'] ?? false),
            'metadata' => (array) ($data['thread_metadata'] ?? []),
        ]);
    }

    /** @return array<string,mixed> */
    private function performAction(Chatbot $chatbot, CommerceCommunicationThread $thread, CommerceCommunicationAction $action): array
    {
        $payload = (array) ($action->payload ?? []);
        $order = null;
        if (isset($payload['order_uuid'])) {
            $order = CommerceOrder::query()
                ->with('paymentIntent')
                ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
                ->where('uuid', (string) $payload['order_uuid'])
                ->where(function ($query) use ($thread): void {
                    if ($thread->session_id) {
                        $query->where('session_id', $thread->session_id);
                    }
                    if ($thread->customer_identity_id) {
                        $thread->session_id
                            ? $query->orWhere('customer_identity_id', $thread->customer_identity_id)
                            : $query->where('customer_identity_id', $thread->customer_identity_id);
                    }
                })
                ->firstOrFail();
        }

        return match ($action->action_type) {
            'request_return', 'prepare_return' => (function () use ($order, $payload, $thread): array {
                if ($order === null) {
                    throw ValidationException::withMessages(['order_uuid' => 'A scoped order is required for a return.']);
                }
                $return = $this->orders->requestReturn(
                    $order,
                    (array) ($payload['items'] ?? []),
                    (string) ($payload['resolution'] ?? 'refund'),
                    isset($payload['reason']) ? (string) $payload['reason'] : null,
                    $thread->customer_identity_id ? (int) $thread->customer_identity_id : null,
                );
                return ['type' => 'return_requested', 'return' => $return->toArray()];
            })(),
            'cancel_order' => (function () use ($order, $action): array {
                if ($order === null) {
                    throw ValidationException::withMessages(['order_uuid' => 'A scoped order is required for cancellation.']);
                }
                $updated = $this->orders->transition($order, 'cancelled', ['communication_action_uuid' => $action->uuid]);
                return ['type' => 'order_cancelled', 'order' => $updated->toArray()];
            })(),
            'issue_refund' => (function () use ($order, $payload, $action): array {
                if ($order === null || $order->paymentIntent === null) {
                    throw ValidationException::withMessages(['order_uuid' => 'The scoped order has no refundable payment.']);
                }
                $refund = $this->payments->refund(
                    $order->paymentIntent,
                    (int) ($payload['amount'] ?? 0),
                    'support:' . $action->uuid,
                    isset($payload['reason']) ? (string) $payload['reason'] : 'Customer communications refund',
                );
                return ['type' => 'payment_refunded', 'refund' => $refund->toArray()];
            })(),
            'correct_address' => (function () use ($order, $payload, $action): array {
                if ($order === null) {
                    throw ValidationException::withMessages(['order_uuid' => 'A scoped order is required for an address correction.']);
                }
                if (in_array((string) $order->fulfillment_status, ['fulfilled', 'delivered'], true)) {
                    throw ValidationException::withMessages(['order_uuid' => 'The delivery address cannot be changed after fulfilment.']);
                }
                $before = $order->shipping_address;
                $order->forceFill(['shipping_address' => (array) ($payload['shipping_address'] ?? [])])->save();
                CommerceOrderEvent::query()->create([
                    'order_id' => $order->id,
                    'event_type' => 'shipping_address_corrected',
                    'actor_type' => 'customer_communications',
                    'actor_id' => $action->uuid,
                    'before_state' => ['shipping_address' => $before],
                    'after_state' => ['shipping_address' => $order->shipping_address],
                    'metadata' => ['communication_action_uuid' => $action->uuid],
                ]);
                return ['type' => 'address_corrected', 'order' => $order->fresh()->toArray()];
            })(),
            default => throw ValidationException::withMessages(['action_type' => 'The prepared customer-service action is not executable by this extension.']),
        };
    }

    private function policyFor(int $chatbotId): CommerceCommunicationPolicy
    {
        return CommerceCommunicationPolicy::query()->firstOrCreate(
            ['chatbot_id' => $chatbotId],
            [
                'auto_reply_enabled' => (bool) config('chatbot-ecommerce.customer_communications.auto_reply_enabled', false),
                'auto_reply_confidence' => (float) config('chatbot-ecommerce.customer_communications.auto_reply_confidence', 0.90),
                'automatic_refund_limit' => (int) config('chatbot-ecommerce.customer_communications.automatic_refund_limit', 0),
                'automatic_discount_limit' => (int) config('chatbot-ecommerce.customer_communications.automatic_discount_limit', 0),
                'automatic_store_credit_limit' => (int) config('chatbot-ecommerce.customer_communications.automatic_store_credit_limit', 0),
                'automatic_replacement_limit' => (int) config('chatbot-ecommerce.customer_communications.automatic_replacement_limit', 0),
                'automatic_cancellation_limit' => (int) config('chatbot-ecommerce.customer_communications.automatic_cancellation_limit', 0),
                'currency' => (string) config('chatbot-ecommerce.customer_communications.currency', 'USD'),
                'require_identity_for_order_data' => true,
                'human_handoff' => true,
                'return_window_days' => (int) config('chatbot-ecommerce.orders.return_window_days', 30),
                'allowed_auto_actions' => ['answer_product_question', 'explain_policy', 'check_stock'],
                'escalation_triggers' => ['legal_threat', 'fraud_suspected', 'chargeback_dispute', 'safety_incident'],
                'enabled_channels' => ['web', 'email', 'sms', 'whatsapp', 'telegram', 'messenger', 'instagram', 'marketplace_message', 'internal_inbox'],
                'active' => true,
            ],
        );
    }

    /** @return array<string,int> */
    private function policyLimits(CommerceCommunicationPolicy $policy): array
    {
        return [
            'automatic_refund_limit' => (int) $policy->automatic_refund_limit,
            'automatic_discount_limit' => (int) $policy->automatic_discount_limit,
            'automatic_store_credit_limit' => (int) $policy->automatic_store_credit_limit,
            'automatic_replacement_limit' => (int) $policy->automatic_replacement_limit,
            'automatic_cancellation_limit' => (int) $policy->automatic_cancellation_limit,
        ];
    }

    /** @param array<string,mixed> $context */
    private function replyText(string $intent, array $context, bool $identityVerified): string
    {
        if (in_array($intent, ['order_status', 'tracking', 'delivery_delay', 'refund_status', 'return_status', 'payment_problem'], true) && ! $identityVerified) {
            return 'I can help with that, but I need to verify the customer before sharing private order or payment details.';
        }

        $orders = (array) ($context['orders'] ?? []);
        $latestOrder = $orders[0] ?? null;
        $payments = (array) ($context['payments'] ?? []);
        $fulfillments = (array) ($context['fulfillments'] ?? []);
        $returns = (array) ($context['returns'] ?? []);

        return match ($intent) {
            'order_status' => is_array($latestOrder)
                ? sprintf('Order %s is currently %s.', $latestOrder['order_number'] ?? $latestOrder['uuid'], $latestOrder['status'] ?? 'unknown')
                : 'I could not find an order linked to this verified conversation.',
            'tracking', 'delivery_delay' => isset($fulfillments[0])
                ? sprintf('The latest fulfilment is %s%s.', $fulfillments[0]['status'] ?? 'pending', ! empty($fulfillments[0]['tracking_number']) ? ' with tracking number ' . $fulfillments[0]['tracking_number'] : '')
                : 'I could not find a shipment linked to this order yet.',
            'refund_status', 'payment_problem' => isset($payments[0])
                ? sprintf('The latest payment is %s. Captured: %s %.2f; refunded: %s %.2f.', $payments[0]['status'] ?? 'unknown', $payments[0]['currency'] ?? 'USD', ((int) ($payments[0]['captured_amount'] ?? 0)) / 100, $payments[0]['currency'] ?? 'USD', ((int) ($payments[0]['refunded_amount'] ?? 0)) / 100)
                : 'I could not find a payment linked to this verified customer.',
            'return_status', 'return', 'exchange' => isset($returns[0])
                ? sprintf('Return %s is currently %s.', $returns[0]['rma_number'] ?? $returns[0]['uuid'], $returns[0]['status'] ?? 'unknown')
                : 'I could not find an existing return. I can prepare a return request for approval.',
            'cancel_order' => 'I can prepare an order-cancellation request. It will be checked against the seller’s approval policy before execution.',
            'legal_threat', 'fraud_suspected', 'chargeback_dispute' => 'I’m escalating this conversation to a person who can review it safely.',
            default => 'I can help with product questions, checkout, payment, delivery, returns, exchanges, refunds, and order support. I will verify the relevant commerce records before making factual claims.',
        };
    }

    /** @param array<string,mixed> $context @return array<int,array<string,mixed>> */
    private function suggestedActions(string $intent, array $context): array
    {
        $latestOrder = ((array) ($context['orders'] ?? []))[0] ?? null;
        $orderUuid = is_array($latestOrder) ? ($latestOrder['uuid'] ?? null) : null;

        return match ($intent) {
            'return', 'exchange' => $orderUuid ? [['action_type' => 'prepare_return', 'order_uuid' => $orderUuid]] : [],
            'cancel_order' => $orderUuid ? [['action_type' => 'cancel_order', 'order_uuid' => $orderUuid]] : [],
            'refund_status', 'payment_problem' => $orderUuid ? [['action_type' => 'issue_refund', 'order_uuid' => $orderUuid, 'requires_amount' => true]] : [],
            'address_correction' => $orderUuid ? [['action_type' => 'correct_address', 'order_uuid' => $orderUuid]] : [],
            default => [],
        };
    }

    private function authorityActionForIntent(string $intent): string
    {
        return match ($intent) {
            'order_status' => 'get_order_status',
            'tracking', 'delivery_delay' => 'get_tracking',
            'refund_status' => 'get_refund_status',
            'return_status' => 'get_return_status',
            'payment_problem' => 'get_payment_status',
            'legal_threat', 'fraud_suspected', 'chargeback_dispute', 'safety_incident' => $intent,
            default => 'answer_product_question',
        };
    }

    private function classifyIntent(string $body): string
    {
        $text = mb_strtolower($body);
        $patterns = [
            'legal_threat' => ['lawyer', 'legal action', 'sue ', 'consumer affairs'],
            'fraud_suspected' => ['fraud', 'stolen card', 'not my purchase'],
            'chargeback_dispute' => ['chargeback', 'bank dispute'],
            'delivery_delay' => ['late', 'delayed', 'not arrived', 'where is my order'],
            'tracking' => ['tracking', 'track my'],
            'return' => ['return', 'send it back'],
            'exchange' => ['exchange', 'different size', 'swap'],
            'refund_status' => ['refund status', 'where is my refund'],
            'cancel_order' => ['cancel my order', 'cancel order'],
            'address_correction' => ['wrong address', 'change address', 'correct address'],
            'payment_problem' => ['payment failed', 'charged twice', 'payment problem'],
            'damaged_item' => ['damaged', 'broken', 'defective'],
            'missing_item' => ['missing item', 'item missing'],
            'order_status' => ['order status', 'my order'],
            'checkout_problem' => ['checkout', 'coupon not working', 'cannot buy'],
            'warranty' => ['warranty', 'guarantee'],
            'complaint' => ['complaint', 'unhappy', 'terrible service'],
        ];
        foreach ($patterns as $intent => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $intent;
                }
            }
        }

        return 'product_question';
    }

    private function defaultConfidence(string $intent): float
    {
        return $intent === 'product_question' ? 0.65 : 0.88;
    }

    private function priorityFor(string $intent, string $body): string
    {
        if (in_array($intent, ['legal_threat', 'fraud_suspected', 'chargeback_dispute', 'safety_incident'], true)) {
            return 'urgent';
        }
        if (in_array($intent, ['delivery_delay', 'damaged_item', 'missing_item', 'payment_problem'], true) || str_contains(mb_strtolower($body), 'urgent')) {
            return 'high';
        }

        return 'normal';
    }

    private function sentimentFor(string $body): string
    {
        $text = mb_strtolower($body);
        foreach (['angry', 'furious', 'terrible', 'disgusted', 'unacceptable', 'hate'] as $negative) {
            if (str_contains($text, $negative)) {
                return 'negative';
            }
        }

        return 'neutral';
    }

    private function assertThreadScope(Chatbot $chatbot, CommerceCommunicationThread $thread): void
    {
        if ((int) $thread->chatbot_id !== (int) $chatbot->getAttribute('id')) {
            throw ValidationException::withMessages(['thread' => 'The communication thread is outside this chatbot scope.']);
        }
        if ((string) $thread->role !== CommerceRole::CUSTOMER_COMMUNICATIONS) {
            throw ValidationException::withMessages(['thread' => 'The thread is not a customer communications thread.']);
        }
    }

    /** @param array<string,mixed> $properties @param array<int,string> $required @return array<string,mixed> */
    private function definition(string $name, string $level, string $description, array $properties, array $required): array
    {
        return ['name' => $name, 'description' => "[{$level}] {$description}", 'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => $required]];
    }
}
