<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\OrderException;
use App\Extensions\ChatbotEcommerce\System\Models\UnifiedCommerceOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class OrderWorkbenchToolRuntime
{
    public function __construct(private readonly UnifiedOrderWorkbenchRuntime $workbench) {}

    /** @return array<int,array<string,mixed>> */
    public function toolDefinitions(): array
    {
        return [
            ['name' => 'seller_order_workbench_list', 'description' => '[inform] List unified native and external orders, including unfulfilled, unreconciled or exceptional orders.', 'parameters' => ['type' => 'object', 'properties' => ['source_type' => ['type' => 'string'], 'status' => ['type' => 'string'], 'fulfillment_status' => ['type' => 'string'], 'payment_status' => ['type' => 'string'], 'unreconciled_only' => ['type' => 'boolean'], 'exceptions_only' => ['type' => 'boolean'], 'search' => ['type' => 'string'], 'limit' => ['type' => 'integer']]]],
            ['name' => 'seller_order_workbench_sync', 'description' => '[prepare] Refresh the unified order projection and detect current exceptions.', 'parameters' => ['type' => 'object', 'properties' => ['seller_approved' => ['type' => 'boolean']], 'required' => ['seller_approved']]],
            ['name' => 'seller_order_prepare_refund', 'description' => '[prepare] Prepare a refund proposal. This never executes a native or external refund.', 'parameters' => ['type' => 'object', 'properties' => ['order_uuid' => ['type' => 'string'], 'exception_uuid' => ['type' => 'string'], 'amount' => ['type' => 'integer'], 'reason' => ['type' => 'string'], 'idempotency_key' => ['type' => 'string']], 'required' => ['order_uuid', 'amount', 'reason', 'idempotency_key']]],
            ['name' => 'seller_order_prepare_customer_contact', 'description' => '[prepare] Prepare a grounded customer-contact draft for an order exception using the existing channel transport boundary.', 'parameters' => ['type' => 'object', 'properties' => ['order_uuid' => ['type' => 'string'], 'exception_uuid' => ['type' => 'string'], 'channel' => ['type' => 'string'], 'idempotency_key' => ['type' => 'string']], 'required' => ['order_uuid', 'idempotency_key']]],
        ];
    }

    /** @param array<string,mixed> $arguments @return array<string,mixed> */
    public function execute(Chatbot $chatbot, string $function, array $arguments): array
    {
        $this->assertSellerOwner($chatbot);
        if ($function === 'seller_order_workbench_list') {
            $limit = min(100, max(1, (int) ($arguments['limit'] ?? 25)));
            $orders = $this->workbench->orders($chatbot, $arguments, $limit);
            return ['data' => collect($orders->items())->map(static fn (UnifiedCommerceOrder $order): array => [
                'uuid' => $order->uuid, 'source_type' => $order->source_type, 'source_order_id' => $order->source_order_id,
                'status' => $order->status, 'fulfillment_status' => $order->fulfillment_status, 'payment_status' => $order->payment_status,
                'currency' => $order->currency, 'gross_total' => (int) $order->gross_total,
                'reconciliation_status' => $order->reconciliation_status, 'settlement_variance' => (int) $order->settlement_variance,
                'open_exceptions' => $order->exceptions->map(fn (OrderException $exception): array => ['uuid' => $exception->uuid, 'type' => $exception->exception_type, 'severity' => $exception->severity, 'summary' => $exception->summary])->all(),
            ])->all()];
        }
        if ($function === 'seller_order_workbench_sync') {
            if (! (bool) ($arguments['seller_approved'] ?? false)) {
                throw ValidationException::withMessages(['seller_approved' => 'Explicit seller approval is required before refreshing external and native order projections.']);
            }
            return ['data' => $this->workbench->sync($chatbot)];
        }

        $order = UnifiedCommerceOrder::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('uuid', (string) ($arguments['order_uuid'] ?? ''))->firstOrFail();
        $exception = null;
        if (! empty($arguments['exception_uuid'])) {
            $exception = OrderException::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('unified_order_id', $order->id)->where('uuid', (string) $arguments['exception_uuid'])->firstOrFail();
        }
        if ($function === 'seller_order_prepare_refund') {
            return ['data' => $this->workbench->prepareRefundProposal($chatbot, $order, $exception, (int) ($arguments['amount'] ?? 0), (string) ($arguments['reason'] ?? ''), (string) ($arguments['idempotency_key'] ?? ''), (int) Auth::id())];
        }
        if ($function === 'seller_order_prepare_customer_contact') {
            return ['data' => $this->workbench->prepareCustomerContact($chatbot, $order, $exception, (string) ($arguments['channel'] ?? 'connected_inbox'), (string) ($arguments['idempotency_key'] ?? ''), (int) Auth::id())];
        }
        throw ValidationException::withMessages(['tool' => 'Unsupported seller order workbench tool.']);
    }

    private function assertSellerOwner(Chatbot $chatbot): void
    {
        if (! Auth::check() || (int) $chatbot->getAttribute('user_id') !== (int) Auth::id()) {
            throw ValidationException::withMessages(['role' => 'Only the authenticated chatbot owner can use seller order workbench tools.']);
        }
    }
}
