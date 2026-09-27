<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationThread;
use App\Extensions\ChatbotEcommerce\System\Models\OrderException;
use App\Extensions\ChatbotEcommerce\System\Models\OrderWorkbenchAction as OrderWorkbenchActionModel;
use App\Extensions\ChatbotEcommerce\System\Models\UnifiedCommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Support\OrderWorkbenchAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UnifiedOrderWorkbenchRuntime
{
    public function __construct(
        private readonly UnifiedOrderProjectorRuntime $projector,
        private readonly OrderSettlementRuntime $settlements,
        private readonly OrderExceptionRuntime $exceptions,
    ) {}

    /** @param array<string,mixed> $filters */
    public function orders(Chatbot $chatbot, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = UnifiedCommerceOrder::query()
            ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
            ->with(['exceptions' => static fn ($q) => $q->whereIn('status', ['open', 'acknowledged']), 'communicationThreads'])
            ->orderByDesc('placed_at')->orderByDesc('id');

        foreach (['source_type', 'status', 'fulfillment_status', 'payment_status', 'reconciliation_status'] as $field) {
            if (($filters[$field] ?? null) !== null && trim((string) $filters[$field]) !== '') {
                $query->where($field, trim((string) $filters[$field]));
            }
        }
        if ((bool) ($filters['external_only'] ?? false)) { $query->where('external_authoritative', true); }
        if ((bool) ($filters['unreconciled_only'] ?? false)) { $query->where('reconciliation_status', '!=', 'reconciled'); }
        if ((bool) ($filters['exceptions_only'] ?? false)) {
            $query->whereHas('exceptions', static fn (Builder $q) => $q->whereIn('status', ['open', 'acknowledged']));
        }
        if (($filters['search'] ?? '') !== '') {
            $search = trim((string) $filters['search']);
            $query->where(static function (Builder $q) use ($search): void {
                $q->where('source_order_id', 'like', '%' . $search . '%')
                    ->orWhere('buyer_display_name', 'like', '%' . $search . '%')
                    ->orWhere('uuid', $search);
            });
        }

        return $query->paginate(min(100, max(1, $perPage)));
    }

    public function show(Chatbot $chatbot, UnifiedCommerceOrder $order): UnifiedCommerceOrder
    {
        $this->assertScope($chatbot, $order);
        return $order->load(['sourceSnapshots', 'settlements', 'reconciliations', 'exceptions.events', 'actions', 'communicationThreads']);
    }

    /** @return array<string,mixed> */
    public function sync(Chatbot $chatbot): array
    {
        $projection = $this->projector->syncChatbot($chatbot);
        $exceptions = $this->exceptions->scanChatbot($chatbot);
        return ['projection' => $projection, 'exceptions' => $exceptions];
    }

    public function acknowledge(Chatbot $chatbot, OrderException $exception, int $userId, string $idempotencyKey): OrderWorkbenchActionModel
    {
        $idempotencyKey = $this->requireIdempotencyKey($idempotencyKey);
        return $this->recordLowRiskAction($chatbot, $exception->order()->firstOrFail(), $exception, 'acknowledge', $idempotencyKey, $userId, function () use ($chatbot, $exception, $userId): array {
            return $this->exceptions->acknowledge($chatbot, $exception, $userId)->toArray();
        });
    }

    public function assign(Chatbot $chatbot, OrderException $exception, int $assigneeUserId, int $actorUserId, string $idempotencyKey): OrderWorkbenchActionModel
    {
        $idempotencyKey = $this->requireIdempotencyKey($idempotencyKey);
        return $this->recordLowRiskAction($chatbot, $exception->order()->firstOrFail(), $exception, 'assign', $idempotencyKey, $actorUserId, function () use ($chatbot, $exception, $assigneeUserId, $actorUserId): array {
            return $this->exceptions->assign($chatbot, $exception, $assigneeUserId, $actorUserId)->toArray();
        }, ['assignee_user_id' => $assigneeUserId], $assigneeUserId);
    }

    public function resolve(Chatbot $chatbot, OrderException $exception, int $userId, string $idempotencyKey, ?string $note = null): OrderWorkbenchActionModel
    {
        $idempotencyKey = $this->requireIdempotencyKey($idempotencyKey);
        return $this->recordLowRiskAction($chatbot, $exception->order()->firstOrFail(), $exception, 'resolve', $idempotencyKey, $userId, function () use ($chatbot, $exception, $userId, $note): array {
            return $this->exceptions->resolve($chatbot, $exception, $userId, $note)->toArray();
        }, ['note' => $note]);
    }

    public function prepareRefundProposal(Chatbot $chatbot, UnifiedCommerceOrder $order, ?OrderException $exception, int $amount, string $reason, string $idempotencyKey, int $userId): OrderWorkbenchActionModel
    {
        $idempotencyKey = $this->requireIdempotencyKey($idempotencyKey);
        $this->assertScope($chatbot, $order);
        if (! OrderWorkbenchAction::requiresApproval('refund_proposal')) {
            throw ValidationException::withMessages(['action' => 'Refund proposal governance is unavailable.']);
        }
        if ($amount < 1 || $amount > max(0, (int) $order->gross_total - (int) $order->refund_total)) {
            throw ValidationException::withMessages(['amount' => 'Refund proposal amount exceeds the currently refundable order amount.']);
        }
        if ($exception !== null) { $this->assertExceptionScope($chatbot, $exception, $order); }

        return $this->firstOrCreateAction($chatbot, $order, $exception, 'refund_proposal', $idempotencyKey, [
            'amount' => $amount,
            'currency' => $order->currency,
            'reason' => trim($reason),
            'source_type' => $order->source_type,
            'external_authoritative' => (bool) $order->external_authoritative,
            'required_next_step' => $order->external_authoritative ? 'Execute refund in the authoritative external provider after seller approval.' : 'Use the native payment refund workflow after seller approval.',
        ], $userId, 'awaiting_approval', $amount, (string) $order->currency);
    }

    public function prepareCustomerContact(Chatbot $chatbot, UnifiedCommerceOrder $order, ?OrderException $exception, string $channel, string $idempotencyKey, int $userId): OrderWorkbenchActionModel
    {
        $idempotencyKey = $this->requireIdempotencyKey($idempotencyKey);
        $this->assertScope($chatbot, $order);
        if ($exception !== null) { $this->assertExceptionScope($chatbot, $exception, $order); }
        $channel = strtolower(trim($channel)) ?: 'connected_inbox';
        $summary = $exception?->summary ?: 'Order follow-up requested.';
        $draft = sprintf(
            'Hello%s, we are reviewing order %s. %s We will confirm the next step through this channel.',
            $order->buyer_display_name ? ' ' . $order->buyer_display_name : '',
            $order->source_order_id,
            $summary
        );

        return $this->firstOrCreateAction($chatbot, $order, $exception, 'prepare_customer_contact', $idempotencyKey, [
            'channel' => $channel,
            'draft' => $draft,
            'facts' => [
                'source_type' => $order->source_type,
                'order_id' => $order->source_order_id,
                'status' => $order->status,
                'fulfillment_status' => $order->fulfillment_status,
                'payment_status' => $order->payment_status,
            ],
            'transport_boundary' => 'Send through the existing chatbot-agent or channel extension; ecommerce stores no channel credentials.',
        ], $userId, 'prepared');
    }

    public function linkCommunicationThread(Chatbot $chatbot, UnifiedCommerceOrder $order, CommerceCommunicationThread $thread, int $userId, string $idempotencyKey): OrderWorkbenchActionModel
    {
        $idempotencyKey = $this->requireIdempotencyKey($idempotencyKey);
        $this->assertScope($chatbot, $order);
        abort_unless((int) $thread->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        return $this->recordLowRiskAction(
            $chatbot,
            $order,
            null,
            'link_communication_thread',
            $idempotencyKey,
            $userId,
            function () use ($thread, $order): array {
                $thread->forceFill(['unified_order_id' => $order->id])->save();
                return ['thread_uuid' => $thread->uuid, 'linked_at' => now()->toIso8601String()];
            },
            ['thread_uuid' => $thread->uuid],
            null,
            $thread->id,
        );
    }

    public function reconcile(Chatbot $chatbot, UnifiedCommerceOrder $order): UnifiedCommerceOrder
    {
        $this->settlements->reconcile($chatbot, $order);
        $this->exceptions->scanOrder($chatbot, $order->fresh());
        return $this->show($chatbot, $order->fresh());
    }

    /** @param callable():array<string,mixed> $operation @param array<string,mixed> $proposal */
    private function recordLowRiskAction(Chatbot $chatbot, UnifiedCommerceOrder $order, ?OrderException $exception, string $type, string $idempotencyKey, int $userId, callable $operation, array $proposal = [], ?int $assignee = null, ?int $threadId = null): OrderWorkbenchActionModel
    {
        $this->assertScope($chatbot, $order);
        if ($exception !== null) { $this->assertExceptionScope($chatbot, $exception, $order); }
        $requestHash = $this->actionRequestHash($order, $exception, $type, $proposal, $threadId);
        $existing = OrderWorkbenchActionModel::query()
            ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) { return $this->assertIdempotencyMatch($existing, $order, $exception, $type, $requestHash); }

        try {
            return DB::transaction(function () use ($chatbot, $order, $exception, $type, $idempotencyKey, $requestHash, $userId, $operation, $proposal, $assignee, $threadId): OrderWorkbenchActionModel {
                $action = OrderWorkbenchActionModel::query()->create([
                    'chatbot_id' => (int) $chatbot->getAttribute('id'),
                    'unified_order_id' => $order->id,
                    'exception_id' => $exception?->id,
                    'communication_thread_id' => $threadId,
                    'action_type' => $type,
                    'status' => 'processing',
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'requested_by_user_id' => $userId,
                    'assigned_to_user_id' => $assignee,
                    'source_hash' => $order->source_hash,
                    'proposal' => $proposal,
                ]);
                $result = $operation();
                $action->forceFill(['status' => 'completed', 'result' => $result, 'executed_at' => now()])->save();
                return $action->fresh();
            });
        } catch (QueryException $exceptionThrown) {
            $existing = OrderWorkbenchActionModel::query()
                ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) { return $this->assertIdempotencyMatch($existing, $order, $exception, $type, $requestHash); }
            throw $exceptionThrown;
        }
    }

    /** @param array<string,mixed> $proposal @param array<string,mixed>|null $result */
    private function firstOrCreateAction(Chatbot $chatbot, UnifiedCommerceOrder $order, ?OrderException $exception, string $type, string $idempotencyKey, array $proposal, int $userId, string $status, ?int $amount = null, ?string $currency = null, ?int $threadId = null, ?array $result = null): OrderWorkbenchActionModel
    {
        $requestHash = $this->actionRequestHash($order, $exception, $type, $proposal, $threadId);
        $existing = OrderWorkbenchActionModel::query()
            ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) { return $this->assertIdempotencyMatch($existing, $order, $exception, $type, $requestHash); }

        try {
            return OrderWorkbenchActionModel::query()->create([
                'chatbot_id' => (int) $chatbot->getAttribute('id'),
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'unified_order_id' => $order->id,
                'exception_id' => $exception?->id,
                'communication_thread_id' => $threadId,
                'action_type' => $type,
                'status' => $status,
                'requested_by_user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'source_hash' => $order->source_hash,
                'proposal' => $proposal,
                'result' => $result,
                'expires_at' => $status === 'awaiting_approval' ? now()->addMinutes((int) config('chatbot-ecommerce.order_workbench.proposal_ttl_minutes', 30)) : null,
                'executed_at' => in_array($status, ['completed'], true) ? now() : null,
            ]);
        } catch (QueryException $exceptionThrown) {
            $existing = OrderWorkbenchActionModel::query()
                ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) { return $this->assertIdempotencyMatch($existing, $order, $exception, $type, $requestHash); }
            throw $exceptionThrown;
        }
    }

    /** @param array<string,mixed> $proposal */
    private function actionRequestHash(UnifiedCommerceOrder $order, ?OrderException $exception, string $type, array $proposal, ?int $threadId): string
    {
        $payload = [
            'unified_order_id' => (int) $order->id,
            'exception_id' => $exception?->id !== null ? (int) $exception->id : null,
            'communication_thread_id' => $threadId,
            'action_type' => $type,
            'proposal' => $this->canonicalize($proposal),
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '');
    }

    private function assertIdempotencyMatch(OrderWorkbenchActionModel $existing, UnifiedCommerceOrder $order, ?OrderException $exception, string $type, string $requestHash): OrderWorkbenchActionModel
    {
        $matches = (int) $existing->unified_order_id === (int) $order->id
            && ($existing->exception_id === null ? $exception === null : (int) $existing->exception_id === (int) $exception?->id)
            && (string) $existing->action_type === $type
            && hash_equals((string) $existing->request_hash, $requestHash);
        if (! $matches) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key was already used for a different order workbench request.']);
        }
        return $existing;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) { return $value; }
        if (! array_is_list($value)) { ksort($value); }
        foreach ($value as $key => $item) { $value[$key] = $this->canonicalize($item); }
        return $value;
    }


    private function requireIdempotencyKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || mb_strlen($key) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'A non-empty idempotency key of at most 191 characters is required.']);
        }
        return $key;
    }

    private function assertScope(Chatbot $chatbot, UnifiedCommerceOrder $order): void { abort_unless((int) $order->chatbot_id === (int) $chatbot->getAttribute('id'), 404); }
    private function assertExceptionScope(Chatbot $chatbot, OrderException $exception, UnifiedCommerceOrder $order): void
    {
        abort_unless((int) $exception->chatbot_id === (int) $chatbot->getAttribute('id') && (int) $exception->unified_order_id === (int) $order->id, 404);
    }
}
