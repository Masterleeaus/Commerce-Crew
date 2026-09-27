<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\OrderExceptionResource;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\UnifiedCommerceOrderResource;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationThread;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\OrderException;
use App\Extensions\ChatbotEcommerce\System\Models\UnifiedCommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Services\OrderExceptionRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\OrderSettlementRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\UnifiedOrderProjectorRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\UnifiedOrderWorkbenchRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class UnifiedOrderWorkbenchAdminApiController extends Controller
{
    public function index(Chatbot $chatbot, Request $request, UnifiedOrderWorkbenchRuntime $runtime): JsonResponse
    {
        $filters = $request->only(['source_type', 'status', 'fulfillment_status', 'payment_status', 'reconciliation_status', 'external_only', 'unreconciled_only', 'exceptions_only', 'search']);
        $orders = $runtime->orders($chatbot, $filters, $request->integer('per_page', 50));
        return response()->json([
            'data' => UnifiedCommerceOrderResource::collection($orders->items()),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'per_page' => $orders->perPage(), 'total' => $orders->total()],
        ]);
    }

    public function show(Chatbot $chatbot, UnifiedCommerceOrder $unifiedOrder, UnifiedOrderWorkbenchRuntime $runtime): UnifiedCommerceOrderResource
    {
        return new UnifiedCommerceOrderResource($runtime->show($chatbot, $unifiedOrder));
    }

    public function sync(Chatbot $chatbot, UnifiedOrderWorkbenchRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $runtime->sync($chatbot)], 202);
    }

    public function importExternal(Chatbot $chatbot, Request $request, UnifiedOrderProjectorRuntime $projector, OrderExceptionRuntime $exceptions): UnifiedCommerceOrderResource
    {
        $validated = $request->validate([
            'source_type' => ['required', 'string', 'in:shopify,woocommerce,amazon,ebay,etsy,generic'],
            'source_scope' => ['required', 'string', 'max:191'],
            'source_order_id' => ['required', 'string', 'max:191'],
            'connection_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'snapshot' => ['required', 'array'],
        ]);
        $connectionId = $validated['connection_id'] ?? null;
        if ($connectionId !== null) {
            $connection = MarketplaceConnection::query()
                ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
                ->whereKey((int) $connectionId)
                ->firstOrFail();
            if (in_array($validated['source_type'], ['amazon', 'ebay', 'etsy'], true)) {
                abort_unless(strtolower((string) $connection->provider) === $validated['source_type'], 404);
            }
        }
        $order = $projector->projectExternal($chatbot, $validated['source_type'], $validated['source_scope'], $validated['source_order_id'], $validated['snapshot'], $connectionId);
        $exceptions->scanOrder($chatbot, $order);
        return new UnifiedCommerceOrderResource($order->fresh(['exceptions']));
    }

    public function importSettlements(Chatbot $chatbot, UnifiedCommerceOrder $unifiedOrder, Request $request, OrderSettlementRuntime $runtime): JsonResponse
    {
        $this->assertOrderScope($chatbot, $unifiedOrder);
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:60'],
            'source_scope' => ['sometimes', 'nullable', 'string', 'max:191'],
            'source_batch_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'entries' => ['required', 'array', 'min:1', 'max:1000'],
            'entries.*.external_entry_id' => ['required', 'string', 'max:191'],
            'entries.*.type' => ['required', 'string', 'max:60'],
            'entries.*.amount' => ['required', 'integer'],
            'entries.*.currency' => ['sometimes', 'string', 'size:3'],
            'entries.*.occurred_at' => ['sometimes', 'nullable', 'date'],
        ]);
        return response()->json(['data' => $runtime->importEntries($chatbot, $unifiedOrder, $validated['provider'], $validated['source_scope'] ?? null, $validated['source_batch_id'] ?? null, $validated['entries'])], 201);
    }

    public function reconcile(Chatbot $chatbot, UnifiedCommerceOrder $unifiedOrder, UnifiedOrderWorkbenchRuntime $runtime): UnifiedCommerceOrderResource
    {
        return new UnifiedCommerceOrderResource($runtime->reconcile($chatbot, $unifiedOrder));
    }

    public function exceptions(Chatbot $chatbot, Request $request): JsonResponse
    {
        $query = OrderException::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->with('order')->orderByDesc('last_detected_at');
        foreach (['status', 'severity', 'exception_type'] as $field) {
            if ($request->filled($field)) { $query->where($field, $request->string($field)->toString()); }
        }
        $rows = $query->paginate(min(100, max(1, $request->integer('per_page', 50))));
        return response()->json(['data' => OrderExceptionResource::collection($rows->items()), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()]]);
    }

    public function showException(Chatbot $chatbot, OrderException $exception): OrderExceptionResource
    {
        $this->assertExceptionScope($chatbot, $exception);
        return new OrderExceptionResource($exception->load(['order', 'events']));
    }

    public function acknowledge(Chatbot $chatbot, OrderException $exception, Request $request, UnifiedOrderWorkbenchRuntime $runtime): JsonResponse
    {
        $this->assertExceptionScope($chatbot, $exception);
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:191']]);
        return response()->json(['data' => $runtime->acknowledge($chatbot, $exception, (int) Auth::id(), $validated['idempotency_key'])]);
    }

    public function assign(Chatbot $chatbot, OrderException $exception, Request $request, UnifiedOrderWorkbenchRuntime $runtime): JsonResponse
    {
        $this->assertExceptionScope($chatbot, $exception);
        $validated = $request->validate(['assigned_user_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:191']]);
        return response()->json(['data' => $runtime->assign($chatbot, $exception, (int) $validated['assigned_user_id'], (int) Auth::id(), $validated['idempotency_key'])]);
    }

    public function resolve(Chatbot $chatbot, OrderException $exception, Request $request, UnifiedOrderWorkbenchRuntime $runtime): JsonResponse
    {
        $this->assertExceptionScope($chatbot, $exception);
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:191'], 'note' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        return response()->json(['data' => $runtime->resolve($chatbot, $exception, (int) Auth::id(), $validated['idempotency_key'], $validated['note'] ?? null)]);
    }

    public function prepareRefund(Chatbot $chatbot, UnifiedCommerceOrder $unifiedOrder, Request $request, UnifiedOrderWorkbenchRuntime $runtime): JsonResponse
    {
        $this->assertOrderScope($chatbot, $unifiedOrder);
        $validated = $request->validate([
            'exception_uuid' => ['sometimes', 'nullable', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $exception = isset($validated['exception_uuid']) ? OrderException::query()->where('chatbot_id', $chatbot->id)->where('unified_order_id', $unifiedOrder->id)->where('uuid', $validated['exception_uuid'])->firstOrFail() : null;
        return response()->json(['data' => $runtime->prepareRefundProposal($chatbot, $unifiedOrder, $exception, (int) $validated['amount'], $validated['reason'], $validated['idempotency_key'], (int) Auth::id())], 201);
    }

    public function prepareCustomerContact(Chatbot $chatbot, UnifiedCommerceOrder $unifiedOrder, Request $request, UnifiedOrderWorkbenchRuntime $runtime): JsonResponse
    {
        $this->assertOrderScope($chatbot, $unifiedOrder);
        $validated = $request->validate([
            'exception_uuid' => ['sometimes', 'nullable', 'uuid'],
            'channel' => ['sometimes', 'string', 'max:60'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $exception = isset($validated['exception_uuid']) ? OrderException::query()->where('chatbot_id', $chatbot->id)->where('unified_order_id', $unifiedOrder->id)->where('uuid', $validated['exception_uuid'])->firstOrFail() : null;
        return response()->json(['data' => $runtime->prepareCustomerContact($chatbot, $unifiedOrder, $exception, $validated['channel'] ?? 'connected_inbox', $validated['idempotency_key'], (int) Auth::id())], 201);
    }

    public function linkThread(Chatbot $chatbot, UnifiedCommerceOrder $unifiedOrder, CommerceCommunicationThread $thread, Request $request, UnifiedOrderWorkbenchRuntime $runtime): JsonResponse
    {
        $this->assertOrderScope($chatbot, $unifiedOrder);
        abort_unless((int) $thread->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:191']]);
        return response()->json(['data' => $runtime->linkCommunicationThread($chatbot, $unifiedOrder, $thread, (int) Auth::id(), $validated['idempotency_key'])]);
    }

    private function assertOrderScope(Chatbot $chatbot, UnifiedCommerceOrder $order): void { abort_unless((int) $order->chatbot_id === (int) $chatbot->getAttribute('id'), 404); }
    private function assertExceptionScope(Chatbot $chatbot, OrderException $exception): void { abort_unless((int) $exception->chatbot_id === (int) $chatbot->getAttribute('id'), 404); }
}
