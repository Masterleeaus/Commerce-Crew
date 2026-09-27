<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\ConversationContext;
use App\Extensions\ChatbotEcommerce\System\Models\Product;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;
use App\Extensions\ChatbotEcommerce\System\Providers\InternalCommerceProvider;
use App\Extensions\ChatbotEcommerce\System\Support\ConversationContextStack;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ConversationalCommerceRuntime
{
    public function __construct(
        private readonly InternalCommerceProvider $commerce,
        private readonly CartRuntime $carts,
        private readonly CheckoutRuntime $checkouts,
        private readonly ConversationContextRuntime $contexts,
        private readonly BudgetLockRuntime $budgets,
        private readonly NativeOrderRuntime $orders,
        private readonly CommerceCardRuntime $cards,
        private readonly FailureTranslationRuntime $failures,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function toolDefinitions(): array
    {
        return [
            $this->definition('native_search_products', 'inform', 'Search the native catalogue and remember the exact result set.', ['query' => ['type' => 'string'], 'filters' => ['type' => 'object'], 'session_id' => ['type' => 'string']], ['session_id']),
            $this->definition('native_get_product', 'inform', 'Get an exact product. Never guess an ambiguous product reference.', ['product_id' => ['type' => 'integer'], 'session_id' => ['type' => 'string']], ['session_id']),
            $this->definition('native_compare_products', 'inform', 'Compare selected products using factual catalogue data.', ['product_ids' => ['type' => 'array', 'items' => ['type' => 'integer']], 'session_id' => ['type' => 'string']], ['session_id']),
            $this->definition('native_get_cart', 'inform', 'Read the current native cart.', ['session_id' => ['type' => 'string']], ['session_id']),
            $this->definition('native_prepare_add_to_cart', 'prepare', 'Prepare a cart addition and return an approval card.', ['product_id' => ['type' => 'integer'], 'variant_id' => ['type' => 'integer'], 'quantity' => ['type' => 'integer', 'minimum' => 1], 'session_id' => ['type' => 'string']], ['session_id', 'quantity']),
            $this->definition('native_prepare_apply_coupon', 'prepare', 'Prepare a coupon change and return an approval card.', ['code' => ['type' => 'string'], 'session_id' => ['type' => 'string']], ['session_id', 'code']),
            $this->definition('native_prepare_checkout', 'prepare', 'Prepare checkout after budget validation.', ['session_id' => ['type' => 'string']], ['session_id']),
            $this->definition('native_get_order', 'inform', 'Retrieve an order and post-purchase status.', ['order_number' => ['type' => 'string'], 'session_id' => ['type' => 'string']], ['session_id', 'order_number']),
            $this->definition('native_prepare_return', 'prepare', 'Prepare a return request for explicit approval.', ['order_number' => ['type' => 'string'], 'items' => ['type' => 'array'], 'resolution' => ['type' => 'string'], 'reason' => ['type' => 'string'], 'session_id' => ['type' => 'string']], ['session_id', 'order_number', 'items', 'resolution']),
        ];
    }

    /** @return array<string,mixed> */
    public function execute(Chatbot $chatbot, string $sessionId, string $tool, array $arguments = []): array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw ValidationException::withMessages(['session_id' => 'A shopping session ID is required.']);
        }

        $context = $this->contexts->getOrCreate((int) $chatbot->getAttribute('id'), $sessionId, [
            'conversation_id' => $arguments['conversation_id'] ?? null,
            'customer_identity_id' => $arguments['customer_identity_id'] ?? null,
        ]);

        try {
            return match ($tool) {
                'native_search_products' => $this->search($chatbot, $context, $arguments),
                'native_get_product' => $this->product($chatbot, $context, $arguments),
                'native_compare_products' => $this->compare($chatbot, $context, $arguments),
                'native_get_cart' => $this->cart($chatbot, $context, $sessionId),
                'native_prepare_add_to_cart' => $this->prepareAdd($chatbot, $context, $sessionId, $arguments),
                'native_prepare_apply_coupon' => $this->prepareCoupon($context, $arguments),
                'native_prepare_checkout' => $this->prepareCheckout($chatbot, $context, $sessionId),
                'native_execute_action' => $this->executeAction($chatbot, $context, $sessionId, $arguments),
                'native_get_order' => $this->getOrder($chatbot, $context, $sessionId, $arguments),
                'native_prepare_return' => $this->prepareReturn($chatbot, $context, $sessionId, $arguments),
                default => throw ValidationException::withMessages(['tool' => 'Unsupported native commerce tool.']),
            };
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return [
                'level' => 'inform',
                'status' => 'failed',
                'error' => $this->failures->fromThrowable($exception, 'internal', (int) $chatbot->getAttribute('id')),
                'progress' => [['stage' => 'failed', 'message' => 'The commerce action could not be completed.']],
            ];
        }
    }

    /** @return array<string,mixed> */
    private function search(Chatbot $chatbot, ConversationContext $context, array $arguments): array
    {
        $filters = (array) ($arguments['filters'] ?? []);
        $filters['q'] = trim((string) ($arguments['query'] ?? $filters['q'] ?? ''));
        $filters['per_page'] = min(max((int) ($filters['per_page'] ?? 8), 1), 20);
        $filters['chatbot_id'] = (int) $chatbot->getAttribute('id');
        $cacheKey = 'chatbot-ecommerce:search:' . (int) $chatbot->getAttribute('id') . ':' . hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
        $payload = Cache::remember($cacheKey, now()->addSeconds((int) config('chatbot-ecommerce.search_cache_ttl_seconds', 300)), function () use ($filters): array {
            $result = $this->commerce->searchProducts($filters);
            return $result instanceof LengthAwarePaginator ? $result->toArray() : ['data' => collect($result)->toArray()];
        });
        $products = array_values((array) ($payload['data'] ?? []));
        $ids = array_values(array_filter(array_map(static fn (array $product): int => (int) ($product['id'] ?? 0), $products)));
        $context = $this->contexts->merge($context, ['current_product_ids' => $ids, 'last_query' => $filters['q'], 'last_filters' => $filters, 'selected_product_id' => count($ids) === 1 ? $ids[0] : null]);
        $card = $this->cards->products($products);

        return [
            'level' => 'inform', 'status' => 'completed', 'data' => $payload, 'ui' => $card,
            'context' => $this->contexts->state($context),
            'progress' => [['stage' => 'searching', 'message' => 'Searching the catalogue…'], ['stage' => 'results', 'message' => 'Found ' . count($products) . ' products.']],
        ];
    }

    /** @return array<string,mixed> */
    private function product(Chatbot $chatbot, ConversationContext $context, array $arguments): array
    {
        $productId = ConversationContextStack::requireProductReference($this->contexts->state($context), $arguments['product_id'] ?? null);
        if ($productId === null) {
            throw ValidationException::withMessages(['product_id' => 'The product reference is ambiguous. Select an exact product first.']);
        }
        $product = $this->commerce instanceof \App\Extensions\ChatbotEcommerce\System\Providers\InternalCommerceProvider
            ? $this->commerce->findProductForChatbot((int) $chatbot->getAttribute('id'), $productId)
            : $this->commerce->findProduct($productId);
        $context = $this->contexts->merge($context, ['selected_product_id' => $productId]);
        return ['level' => 'inform', 'status' => 'completed', 'data' => $product->toArray(), 'ui' => $this->cards->products([$product]), 'context' => $this->contexts->state($context)];
    }

    /** @return array<string,mixed> */
    private function compare(Chatbot $chatbot, ConversationContext $context, array $arguments): array
    {
        $ids = array_values(array_unique(array_map('intval', (array) ($arguments['product_ids'] ?? $context->current_product_ids ?? []))));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if (count($ids) < 2) {
            throw ValidationException::withMessages(['product_ids' => 'Choose at least two exact products to compare.']);
        }
        $products = Product::query()->with(['variants.inventory', 'category'])->where('active', true)->where('chatbot_id', (int) $chatbot->getAttribute('id'))->whereIn('id', array_slice($ids, 0, 4))->get();
        return [
            'level' => 'inform', 'status' => 'completed',
            'data' => $products->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name, 'price' => (int) $product->price, 'currency' => $product->currency, 'description' => $product->short_description ?: $product->description, 'variants' => $product->variants->toArray()])->values()->all(),
            'ui' => ['schema' => ['type' => 'commerce.product_comparison', 'version' => 1, 'products' => $products->toArray()], 'fallback_text' => $this->cards->products($products)['fallback_text']],
        ];
    }

    /** @return array<string,mixed> */
    private function cart(Chatbot $chatbot, ConversationContext $context, string $sessionId): array
    {
        $cart = $this->cartFor($chatbot, $context, $sessionId);
        return ['level' => 'inform', 'status' => 'completed', 'data' => $cart->toArray(), 'ui' => $this->cards->cart($cart), 'context' => $this->contexts->state($context)];
    }

    /** @return array<string,mixed> */
    private function prepareAdd(Chatbot $chatbot, ConversationContext $context, string $sessionId, array $arguments): array
    {
        $productId = ConversationContextStack::requireProductReference($this->contexts->state($context), $arguments['product_id'] ?? null);
        if ($productId === null) {
            throw ValidationException::withMessages(['product_id' => 'Select the exact product before changing the cart.']);
        }
        $product = Product::query()->with('variants')->where('active', true)->findOrFail($productId);
        $variantId = ConversationContextStack::requireVariantReference($this->contexts->state($context), $arguments['variant_id'] ?? null);
        if ($variantId === null && $product->variants->count() === 1) {
            $variantId = (int) $product->variants->first()->id;
        }
        $variant = $variantId ? $product->variants->firstWhere('id', $variantId) : null;
        if (! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'Select an exact variant before changing the cart.']);
        }
        $quantity = min(max((int) ($arguments['quantity'] ?? 1), 1), 999);
        $cart = $this->cartFor($chatbot, $context, $sessionId);
        $this->budgets->assertAllowed((int) $chatbot->getAttribute('id'), $context->customer_identity_id ? (int) $context->customer_identity_id : null, $sessionId, (int) $cart->total + ((int) $variant->price * $quantity), (string) ($variant->currency ?: $cart->currency));
        $prepared = $this->contexts->prepareAction($context, 'add_to_cart', ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $quantity]);
        $card = $this->cards->approval($prepared['action']['uuid'], 'add_to_cart', ['message' => "Add {$quantity} × {$product->name}" , 'product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $quantity], $prepared['approval_token']);
        return ['level' => 'prepare', 'status' => 'awaiting_approval', 'action' => $prepared['action'], 'approval_token' => $prepared['approval_token'], 'ui' => $card, 'context' => $this->contexts->state($prepared['context'])];
    }

    /** @return array<string,mixed> */
    private function prepareCoupon(ConversationContext $context, array $arguments): array
    {
        $code = strtoupper(trim((string) ($arguments['code'] ?? '')));
        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'A coupon code is required.']);
        }
        $prepared = $this->contexts->prepareAction($context, 'apply_coupon', ['code' => $code]);
        return ['level' => 'prepare', 'status' => 'awaiting_approval', 'action' => $prepared['action'], 'approval_token' => $prepared['approval_token'], 'ui' => $this->cards->approval($prepared['action']['uuid'], 'apply_coupon', ['message' => "Apply coupon {$code}"], $prepared['approval_token']), 'context' => $this->contexts->state($prepared['context'])];
    }

    /** @return array<string,mixed> */
    private function prepareCheckout(Chatbot $chatbot, ConversationContext $context, string $sessionId): array
    {
        $cart = $this->cartFor($chatbot, $context, $sessionId);
        if ((int) $cart->line_count < 1) {
            throw ValidationException::withMessages(['cart' => 'The cart is empty.']);
        }
        $this->budgets->assertAllowed((int) $chatbot->getAttribute('id'), $context->customer_identity_id ? (int) $context->customer_identity_id : null, $sessionId, (int) $cart->total, (string) $cart->currency);
        $prepared = $this->contexts->prepareAction($context, 'prepare_checkout', ['cart_uuid' => $cart->uuid, 'cart_version' => (int) $cart->version, 'total' => (int) $cart->total, 'currency' => $cart->currency]);
        return ['level' => 'prepare', 'status' => 'awaiting_approval', 'action' => $prepared['action'], 'approval_token' => $prepared['approval_token'], 'ui' => $this->cards->approval($prepared['action']['uuid'], 'prepare_checkout', ['message' => sprintf('Prepare checkout for %s %.2f', $cart->currency, $cart->total / 100), 'total' => (int) $cart->total, 'currency' => $cart->currency], $prepared['approval_token']), 'context' => $this->contexts->state($prepared['context'])];
    }

    /** @return array<string,mixed> */
    private function prepareReturn(Chatbot $chatbot, ConversationContext $context, string $sessionId, array $arguments): array
    {
        $order = $this->scopedOrder($chatbot, $context, $sessionId, (string) ($arguments['order_number'] ?? ''));
        $payload = ['order_uuid' => $order->uuid, 'order_number' => $order->order_number, 'items' => (array) ($arguments['items'] ?? []), 'resolution' => (string) ($arguments['resolution'] ?? 'refund'), 'reason' => $arguments['reason'] ?? null];
        $prepared = $this->contexts->prepareAction($context, 'request_return', $payload);
        return ['level' => 'prepare', 'status' => 'awaiting_approval', 'action' => $prepared['action'], 'approval_token' => $prepared['approval_token'], 'ui' => $this->cards->approval($prepared['action']['uuid'], 'request_return', ['message' => 'Request a return for order ' . $order->order_number], $prepared['approval_token']), 'context' => $this->contexts->state($prepared['context'])];
    }

    /** @return array<string,mixed> */
    private function executeAction(Chatbot $chatbot, ConversationContext $context, string $sessionId, array $arguments): array
    {
        $action = $this->contexts->consumeApprovedAction($context, (string) ($arguments['action_uuid'] ?? ''), (string) ($arguments['approval_token'] ?? ''));
        $payload = (array) ($action['payload'] ?? []);
        return match ((string) ($action['type'] ?? '')) {
            'add_to_cart' => DB::transaction(function () use ($chatbot, $context, $sessionId, $payload, $action): array {
                $cart = $this->cartFor($chatbot, $context, $sessionId);
                $cart = $this->carts->addLine($cart, (int) $payload['variant_id'], (int) $payload['quantity'], [], ['approved_action_uuid' => $action['uuid']], 'approved:' . $action['uuid']);
                $this->budgets->assertAllowed((int) $chatbot->getAttribute('id'), $context->customer_identity_id ? (int) $context->customer_identity_id : null, $sessionId, (int) $cart->total, (string) $cart->currency);
                return ['level' => 'execute', 'status' => 'completed', 'action_uuid' => $action['uuid'], 'data' => $cart->toArray(), 'ui' => $this->cards->cart($cart)];
            }),
            'apply_coupon' => (function () use ($chatbot, $context, $sessionId, $payload, $action): array {
                $cart = $this->carts->applyCoupon($this->cartFor($chatbot, $context, $sessionId), (string) $payload['code'], 'approved:' . $action['uuid']);
                return ['level' => 'execute', 'status' => 'completed', 'action_uuid' => $action['uuid'], 'data' => $cart->toArray(), 'ui' => $this->cards->cart($cart)];
            })(),
            'prepare_checkout' => (function () use ($chatbot, $context, $sessionId, $action): array {
                $cart = $this->cartFor($chatbot, $context, $sessionId);
                $this->budgets->assertAllowed((int) $chatbot->getAttribute('id'), $context->customer_identity_id ? (int) $context->customer_identity_id : null, $sessionId, (int) $cart->total, (string) $cart->currency);
                $checkout = $this->checkouts->getOrCreate($cart, 'approved:' . $action['uuid'], ['metadata' => ['approved_action_uuid' => $action['uuid']]]);
                return ['level' => 'execute', 'status' => 'completed', 'action_uuid' => $action['uuid'], 'data' => $checkout->toArray(), 'ui' => ['schema' => ['type' => 'commerce.checkout', 'version' => 1, 'checkout_uuid' => $checkout->uuid, 'status' => $checkout->status, 'total' => (int) $checkout->total, 'currency' => $checkout->currency], 'fallback_text' => sprintf('Checkout %s is ready for customer and delivery details. Total: %s %.2f.', $checkout->uuid, $checkout->currency, $checkout->total / 100)]];
            })(),
            'request_return' => (function () use ($chatbot, $context, $sessionId, $payload, $action): array {
                $order = $this->scopedOrder($chatbot, $context, $sessionId, (string) $payload['order_number']);
                $return = $this->orders->requestReturn($order, (array) $payload['items'], (string) $payload['resolution'], isset($payload['reason']) ? (string) $payload['reason'] : null, $context->customer_identity_id ? (int) $context->customer_identity_id : null);
                return ['level' => 'execute', 'status' => 'completed', 'action_uuid' => $action['uuid'], 'data' => $return->toArray(), 'ui' => ['schema' => ['type' => 'commerce.return_status', 'version' => 1, 'rma_number' => $return->rma_number, 'status' => $return->status, 'requested_amount' => (int) $return->requested_amount, 'currency' => $return->currency], 'fallback_text' => sprintf('Return %s has been requested for %s %.2f.', $return->rma_number, $return->currency, $return->requested_amount / 100)]];
            })(),
            default => throw ValidationException::withMessages(['action' => 'The approved action type is unsupported.']),
        };
    }

    /** @return array<string,mixed> */
    private function getOrder(Chatbot $chatbot, ConversationContext $context, string $sessionId, array $arguments): array
    {
        $order = $this->scopedOrder($chatbot, $context, $sessionId, (string) ($arguments['order_number'] ?? ''));
        return ['level' => 'inform', 'status' => 'completed', 'data' => $order->toArray(), 'ui' => $this->cards->order($order)];
    }

    private function cartFor(Chatbot $chatbot, ConversationContext $context, string $sessionId): ChatbotCart
    {
        $cart = $this->carts->getOrCreate((int) $chatbot->getAttribute('id'), $sessionId, (int) $chatbot->getAttribute('user_id'), ['conversation_id' => $context->conversation_id, 'customer_identity_id' => $context->customer_identity_id, 'channel' => 'chat']);
        if ((int) $context->cart_id !== (int) $cart->id) {
            $this->contexts->merge($context, ['cart_id' => $cart->id]);
        }
        return $cart;
    }

    private function scopedOrder(Chatbot $chatbot, ConversationContext $context, string $sessionId, string $orderNumber): CommerceOrder
    {
        return CommerceOrder::query()->with(['items', 'events', 'returns.items'])
            ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
            ->where('order_number', trim($orderNumber))
            ->where(function ($query) use ($context, $sessionId): void {
                $query->where('session_id', $sessionId);
                if ($context->customer_identity_id) {
                    $query->orWhere('customer_identity_id', $context->customer_identity_id);
                }
            })->firstOrFail();
    }

    /** @param array<string,mixed> $properties @param array<int,string> $required @return array<string,mixed> */
    private function definition(string $name, string $level, string $description, array $properties, array $required): array
    {
        return ['name' => $name, 'description' => "[{$level}] {$description}", 'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => $required]];
    }
}
