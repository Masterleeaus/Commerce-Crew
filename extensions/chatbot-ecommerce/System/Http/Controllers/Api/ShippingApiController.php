<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Services\CartRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CheckoutRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\ShippingRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShippingApiController extends Controller
{
    public function options(
        Chatbot $chatbot,
        string $sessionId,
        Request $request,
        CartRuntime $carts,
        CheckoutRuntime $checkouts,
        ShippingRuntime $shipping,
    ): JsonResponse {
        $cart = $carts->getOrCreate(
            (int) $chatbot->getAttribute('id'),
            $sessionId,
            (int) $chatbot->getAttribute('user_id'),
            [
                'conversation_id' => $request->integer('conversation_id') ?: null,
                'customer_identity_id' => $request->integer('customer_identity_id') ?: null,
                'channel' => $request->input('channel'),
            ],
        );
        $checkout = $checkouts->getOrCreate($cart, $this->idempotencyKey($request));
        $quotes = $shipping->quoteForCheckout($checkout, $cart);

        return response()->json([
            'data' => array_map(fn ($quote): array => $shipping->payload($quote), $quotes),
            'cart_version' => (int) $cart->version,
            'pricing_snapshot_hash' => $cart->pricing_snapshot_hash,
        ]);
    }

    private function idempotencyKey(Request $request): ?string
    {
        $value = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
