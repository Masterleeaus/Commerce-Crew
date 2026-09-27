<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Requests\AddCartLineRequest;
use App\Extensions\ChatbotEcommerce\System\Http\Requests\MergeCartRequest;
use App\Extensions\ChatbotEcommerce\System\Http\Requests\PutCartLineRequest;
use App\Extensions\ChatbotEcommerce\System\Http\Requests\RecoverCartRequest;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\ChatbotCartResource;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCartLine;
use App\Extensions\ChatbotEcommerce\System\Providers\InternalCommerceProvider;
use App\Extensions\ChatbotEcommerce\System\Services\CartRuntime;
use App\Extensions\ChatbotEcommerce\System\Support\CartStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class NativeCommerceApiController extends Controller
{
    public function products(Chatbot $chatbot, string $sessionId, Request $request, InternalCommerceProvider $provider): JsonResponse
    {
        return response()->json($provider->searchProducts(array_merge(
            $request->only(['q', 'category_id', 'per_page']),
            ['chatbot_id' => (int) $chatbot->getKey()],
        )));
    }

    public function product(Chatbot $chatbot, string $sessionId, int $product, InternalCommerceProvider $provider): JsonResponse
    {
        return response()->json(['data' => $provider->findProductForChatbot((int) $chatbot->getKey(), $product)]);
    }

    public function cart(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $runtime): ChatbotCartResource
    {
        return new ChatbotCartResource($this->cartFor($chatbot, $sessionId, $request, $runtime));
    }

    public function addLine(Chatbot $chatbot, string $sessionId, AddCartLineRequest $request, CartRuntime $runtime): ChatbotCartResource
    {
        $cart = $this->cartFor($chatbot, $sessionId, $request, $runtime);
        return new ChatbotCartResource($runtime->addLine(
            $cart,
            (int) $request->validated('variant_id'),
            (int) $request->validated('quantity'),
            $request->validated('customisation', []),
            $request->validated('metadata', []),
            $this->idempotencyKey($request),
        ));
    }

    /** Backward-compatible absolute quantity endpoint. */
    public function putLine(Chatbot $chatbot, string $sessionId, PutCartLineRequest $request, CartRuntime $runtime): ChatbotCartResource
    {
        $cart = $this->cartFor($chatbot, $sessionId, $request, $runtime);
        return new ChatbotCartResource($runtime->setLine(
            $cart,
            (int) $request->validated('variant_id'),
            (int) $request->validated('quantity'),
            $request->validated('customisation', []),
            $request->validated('metadata', []),
            $this->idempotencyKey($request),
        ));
    }

    public function updateLine(
        Chatbot $chatbot,
        string $sessionId,
        ChatbotCartLine $line,
        Request $request,
        CartRuntime $runtime,
    ): ChatbotCartResource {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        $cart = $this->cartFor($chatbot, $sessionId, $request, $runtime);
        return new ChatbotCartResource($runtime->updateLine(
            $cart,
            $line,
            (int) $validated['quantity'],
            $this->idempotencyKey($request),
        ));
    }

    public function removeLine(
        Chatbot $chatbot,
        string $sessionId,
        ChatbotCartLine $line,
        Request $request,
        CartRuntime $runtime,
    ): ChatbotCartResource {
        $cart = $this->cartFor($chatbot, $sessionId, $request, $runtime);
        return new ChatbotCartResource($runtime->removeLine($cart, $line, $this->idempotencyKey($request)));
    }

    public function clear(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $runtime): ChatbotCartResource
    {
        return new ChatbotCartResource($runtime->clear(
            $this->cartFor($chatbot, $sessionId, $request, $runtime),
            $this->idempotencyKey($request),
        ));
    }

    public function recalculate(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $runtime): ChatbotCartResource
    {
        return new ChatbotCartResource($runtime->recalculate(
            $this->cartFor($chatbot, $sessionId, $request, $runtime),
            $this->idempotencyKey($request),
        ));
    }

    public function coupon(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $runtime): ChatbotCartResource
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        return new ChatbotCartResource($runtime->applyCoupon(
            $this->cartFor($chatbot, $sessionId, $request, $runtime),
            $validated['code'] ?? null,
            $this->idempotencyKey($request),
        ));
    }

    public function removeCoupon(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $runtime): ChatbotCartResource
    {
        return new ChatbotCartResource($runtime->removeCoupon(
            $this->cartFor($chatbot, $sessionId, $request, $runtime),
            $this->idempotencyKey($request),
        ));
    }

    public function merge(Chatbot $chatbot, string $sessionId, MergeCartRequest $request, CartRuntime $runtime): ChatbotCartResource
    {
        if ($request->validated('source_session_id') === $sessionId) {
            throw ValidationException::withMessages(['source_session_id' => 'The source and target sessions must be different.']);
        }

        $source = ChatbotCart::query()
            ->where('chatbot_id', $chatbot->getAttribute('id'))
            ->where('session_id', $request->validated('source_session_id'))
            ->where('product_source', 'internal')
            ->whereIn('status', [CartStatus::ACTIVE, CartStatus::ABANDONED])
            ->latest('id')
            ->firstOrFail();
        $sourceTokenHash = hash('sha256', (string) $request->validated('source_recovery_token'));
        if (! is_string($source->recovery_token_hash) || ! hash_equals($source->recovery_token_hash, $sourceTokenHash) || ($source->expires_at?->isPast() ?? false)) {
            throw ValidationException::withMessages(['source_recovery_token' => 'The source cart recovery token is invalid or expired.']);
        }
        $target = $this->cartFor($chatbot, $sessionId, $request, $runtime);

        return new ChatbotCartResource($runtime->merge($source, $target, $this->idempotencyKey($request)));
    }

    public function abandon(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $runtime): JsonResponse
    {
        $result = $runtime->abandon(
            $this->cartFor($chatbot, $sessionId, $request, $runtime),
            $this->idempotencyKey($request),
        );

        return response()->json([
            'data' => (new ChatbotCartResource($result['cart']))->resolve($request),
            'recovery_token' => $result['recovery_token'],
        ]);
    }

    public function recover(Chatbot $chatbot, string $sessionId, RecoverCartRequest $request, CartRuntime $runtime): ChatbotCartResource
    {
        return new ChatbotCartResource($runtime->recover(
            (int) $chatbot->getAttribute('id'),
            $sessionId,
            $request->validated('recovery_token'),
        ));
    }

    private function cartFor(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $runtime): ChatbotCart
    {
        return $runtime->getOrCreate(
            (int) $chatbot->getAttribute('id'),
            $sessionId,
            (int) $chatbot->getAttribute('user_id'),
            [
                'conversation_id' => $request->integer('conversation_id') ?: null,
                'customer_identity_id' => $request->integer('customer_identity_id') ?: null,
                'channel' => $request->input('channel'),
            ],
        );
    }

    private function idempotencyKey(Request $request): ?string
    {
        $value = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
