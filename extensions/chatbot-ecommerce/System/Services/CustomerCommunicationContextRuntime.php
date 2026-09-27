<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationThread;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceReturn;
use App\Extensions\ChatbotEcommerce\System\Models\Fulfillment;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentIntent;

final class CustomerCommunicationContextRuntime
{
    /** @return array<string,mixed> */
    public function build(Chatbot $chatbot, CommerceCommunicationThread $thread): array
    {
        $chatbotId = (int) $chatbot->getAttribute('id');
        $sessionId = $thread->session_id ? (string) $thread->session_id : null;
        $customerIdentityId = $thread->customer_identity_id ? (int) $thread->customer_identity_id : null;
        $identityVerified = (bool) $thread->identity_verified;

        $cart = $sessionId === null
            ? null
            : ChatbotCart::query()
                ->where('chatbot_id', $chatbotId)
                ->where('session_id', $sessionId)
                ->orderByDesc('id')
                ->first();

        if (! $identityVerified) {
            return $this->payload($thread, null, $cart?->toArray(), [], [], [], []);
        }

        if ($sessionId === null && $customerIdentityId === null) {
            return $this->payload($thread, null, null, [], [], [], []);
        }

        $orders = CommerceOrder::query()
            ->with(['items', 'events', 'returns.items', 'paymentIntent'])
            ->where('chatbot_id', $chatbotId)
            ->where(function ($query) use ($sessionId, $customerIdentityId): void {
                if ($sessionId !== null) {
                    $query->where('session_id', $sessionId);
                }
                if ($customerIdentityId !== null) {
                    $sessionId !== null
                        ? $query->orWhere('customer_identity_id', $customerIdentityId)
                        : $query->where('customer_identity_id', $customerIdentityId);
                }
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($cart === null && $customerIdentityId !== null) {
            $cart = ChatbotCart::query()
                ->where('chatbot_id', $chatbotId)
                ->where('customer_identity_id', $customerIdentityId)
                ->orderByDesc('id')
                ->first();
        }

        $paymentIntentIds = $orders->pluck('payment_intent_id')->filter()->map(static fn ($id): int => (int) $id)->all();
        if ($customerIdentityId !== null || $paymentIntentIds !== []) {
            $paymentIntents = PaymentIntent::query()
                ->where('chatbot_id', $chatbotId)
                ->where(function ($query) use ($customerIdentityId, $paymentIntentIds): void {
                    if ($customerIdentityId !== null) {
                        $query->where('customer_identity_id', $customerIdentityId);
                    }
                    if ($paymentIntentIds !== []) {
                        $customerIdentityId !== null
                            ? $query->orWhereIn('id', $paymentIntentIds)
                            : $query->whereIn('id', $paymentIntentIds);
                    }
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        } else {
            $paymentIntents = collect();
        }

        $orderIds = $orders->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $checkoutIds = $orders->pluck('checkout_session_id')->filter()->map(static fn ($id): int => (int) $id)->all();
        $cartIds = $orders->pluck('cart_id')->filter()->map(static fn ($id): int => (int) $id)->all();
        if ($cart !== null) {
            $cartIds[] = (int) $cart->id;
        }

        if ($checkoutIds !== [] || $cartIds !== []) {
            $fulfillments = Fulfillment::query()
                ->with('items')
                ->where(function ($query) use ($checkoutIds, $cartIds): void {
                    if ($checkoutIds !== []) {
                        $query->whereIn('checkout_session_id', $checkoutIds);
                    }
                    if ($cartIds !== []) {
                        $checkoutIds !== []
                            ? $query->orWhereIn('cart_id', array_values(array_unique($cartIds)))
                            : $query->whereIn('cart_id', array_values(array_unique($cartIds)));
                    }
                })
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        } else {
            $fulfillments = collect();
        }

        $returns = $orderIds === []
            ? collect()
            : CommerceReturn::query()->with('items')->whereIn('order_id', $orderIds)->orderByDesc('id')->limit(20)->get();

        return $this->payload(
            $thread,
            $customerIdentityId,
            $cart?->toArray(),
            $orders->map->toArray()->all(),
            $paymentIntents->map->toArray()->all(),
            $fulfillments->map->toArray()->all(),
            $returns->map->toArray()->all(),
        );
    }

    /** @param array<string,mixed>|null $cart @param array<int,mixed> $orders @param array<int,mixed> $payments @param array<int,mixed> $fulfillments @param array<int,mixed> $returns @return array<string,mixed> */
    private function payload(CommerceCommunicationThread $thread, ?int $customerIdentityId, ?array $cart, array $orders, array $payments, array $fulfillments, array $returns): array
    {
        return [
            'role' => 'customer_communications',
            'thread' => [
                'uuid' => $thread->uuid,
                'channel' => $thread->channel,
                'status' => $thread->status,
                'intent' => $thread->intent,
                'identity_verified' => (bool) $thread->identity_verified,
            ],
            'customer_identity_id' => $customerIdentityId,
            'active_cart' => $cart,
            'orders' => $orders,
            'payments' => $payments,
            'fulfillments' => $fulfillments,
            'returns' => $returns,
            'context_rules' => [
                'do_not_invent_records' => true,
                'verify_identity_before_private_order_data' => true,
                'financial_actions_require_policy_evaluation' => true,
            ],
        ];
    }
}
