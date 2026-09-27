<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\CommerceOrderResource;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceReturn;
use App\Extensions\ChatbotEcommerce\System\Services\NativeOrderRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\PaymentRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class NativeOrderApiController extends Controller
{
    public function index(Chatbot $chatbot, string $sessionId, Request $request): AnonymousResourceCollection
    {
        $orders = CommerceOrder::query()->with(['items', 'events', 'returns.items'])
            ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
            ->where(function ($query) use ($sessionId, $request): void {
                $query->where('session_id', $sessionId);
                if ($request->integer('customer_identity_id')) {
                    $query->orWhere('customer_identity_id', $request->integer('customer_identity_id'));
                }
            })->orderByDesc('id')->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return CommerceOrderResource::collection($orders);
    }

    public function show(Chatbot $chatbot, string $sessionId, CommerceOrder $order, Request $request): CommerceOrderResource
    {
        $this->assertScope($chatbot, $sessionId, $order, $request);
        return new CommerceOrderResource($order->load(['items', 'events', 'returns.items']));
    }

    public function materialize(Chatbot $chatbot, string $sessionId, CheckoutSession $checkout, NativeOrderRuntime $runtime): CommerceOrderResource
    {
        abort_unless((int) $checkout->chatbot_id === (int) $chatbot->getAttribute('id') && (string) $checkout->session_id === $sessionId, 404);
        return new CommerceOrderResource($runtime->createFromCheckout($checkout));
    }

    public function requestReturn(Chatbot $chatbot, string $sessionId, CommerceOrder $order, Request $request, NativeOrderRuntime $runtime): JsonResponse
    {
        $this->assertScope($chatbot, $sessionId, $order, $request);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.order_item_uuid' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'items.*.condition' => ['sometimes', 'nullable', 'string', 'max:80'],
            'resolution' => ['required', 'in:refund,exchange,store_credit'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'customer_identity_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        $return = $runtime->requestReturn($order, $validated['items'], $validated['resolution'], $validated['reason'] ?? null, $validated['customer_identity_id'] ?? null);
        return response()->json(['data' => $return], 201);
    }

    public function transition(Chatbot $chatbot, CommerceOrder $order, Request $request, NativeOrderRuntime $runtime): CommerceOrderResource
    {
        abort_unless((int) $order->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        $validated = $request->validate(['status' => ['required', 'string', 'max:40'], 'metadata' => ['sometimes', 'array']]);
        return new CommerceOrderResource($runtime->transition($order, $validated['status'], $validated['metadata'] ?? []));
    }

    public function transitionReturn(Chatbot $chatbot, CommerceReturn $return, Request $request, NativeOrderRuntime $runtime): JsonResponse
    {
        abort_unless((int) $return->order()->value('chatbot_id') === (int) $chatbot->getAttribute('id'), 404);
        $validated = $request->validate(['status' => ['required', 'string', 'max:40'], 'metadata' => ['sometimes', 'array']]);
        return response()->json(['data' => $runtime->transitionReturn($return, $validated['status'], $validated['metadata'] ?? [])]);
    }

    public function refundReturn(Chatbot $chatbot, CommerceReturn $return, Request $request, NativeOrderRuntime $orders, PaymentRuntime $payments): JsonResponse
    {
        $order = $return->order()->with('paymentIntent')->firstOrFail();
        abort_unless((int) $order->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        abort_unless($order->paymentIntent !== null, 422, 'The order has no refundable payment intent.');
        $refund = $payments->refund($order->paymentIntent, (int) $validated['amount'], $validated['idempotency_key'], $validated['reason'] ?? 'Approved return refund');
        $updated = $orders->recordRefund($return, (int) $refund->amount, ['payment_refund_uuid' => $refund->uuid, 'provider_refund_id' => $refund->provider_refund_id]);
        return response()->json(['data' => $updated, 'payment_refund' => $refund]);
    }

    private function assertScope(Chatbot $chatbot, string $sessionId, CommerceOrder $order, Request $request): void
    {
        $customerIdentityId = $request->integer('customer_identity_id') ?: null;
        abort_unless((int) $order->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
        abort_unless((string) $order->session_id === $sessionId || ($customerIdentityId !== null && (int) $order->customer_identity_id === $customerIdentityId), 404);
    }
}
