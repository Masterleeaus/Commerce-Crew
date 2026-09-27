<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\CheckoutSessionResource;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Services\CartRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CheckoutRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CheckoutApiController extends Controller
{
    public function show(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $carts, CheckoutRuntime $checkout): CheckoutSessionResource
    {
        return new CheckoutSessionResource($this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkout));
    }

    public function customer(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $carts, CheckoutRuntime $checkout): CheckoutSessionResource
    {
        $data = $request->validate([
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:80', 'required_without:email'],
            'billing_address' => ['sometimes', 'array'],
            'shipping_address' => ['sometimes', 'array'],
            'billing_address.name' => ['nullable', 'string', 'max:255'],
            'billing_address.company' => ['nullable', 'string', 'max:255'],
            'billing_address.line1' => ['nullable', 'string', 'max:255'],
            'billing_address.line2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:120'],
            'billing_address.region' => ['nullable', 'string', 'max:120'],
            'billing_address.postcode' => ['nullable', 'string', 'max:40'],
            'billing_address.country' => ['nullable', 'string', 'size:2'],
            'shipping_address.name' => ['nullable', 'string', 'max:255'],
            'shipping_address.company' => ['nullable', 'string', 'max:255'],
            'shipping_address.line1' => ['nullable', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['nullable', 'string', 'max:120'],
            'shipping_address.region' => ['nullable', 'string', 'max:120'],
            'shipping_address.postcode' => ['nullable', 'string', 'max:40'],
            'shipping_address.country' => ['nullable', 'string', 'size:2'],
            'customer_note' => ['nullable', 'string', 'max:5000'],
            'consent' => ['sometimes', 'array'],
            'tax_exemption_key' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        return new CheckoutSessionResource($checkout->updateCustomer(
            $this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkout),
            $data,
            $this->idempotencyKey($request),
        ));
    }

    public function delivery(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $carts, CheckoutRuntime $checkout): CheckoutSessionResource
    {
        $data = $request->validate([
            'method' => ['required', 'in:delivery,pickup,digital'],
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return new CheckoutSessionResource($checkout->selectDelivery(
            $this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkout),
            $data,
            $this->idempotencyKey($request),
        ));
    }

    public function prepare(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $carts, CheckoutRuntime $checkout): JsonResponse
    {
        $data = $request->validate([
            'expected_cart_version' => ['nullable', 'integer', 'min:0'],
            'expected_pricing_hash' => ['nullable', 'string', 'size:64'],
        ]);
        $result = $checkout->prepare(
            $this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkout),
            isset($data['expected_cart_version']) ? (int) $data['expected_cart_version'] : null,
            $data['expected_pricing_hash'] ?? null,
            $this->requiredIdempotencyKey($request),
        );

        return response()->json([
            'data' => (new CheckoutSessionResource($result['checkout']))->resolve($request),
            'approval_token' => $result['approval_token'],
        ]);
    }

    public function approve(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $carts, CheckoutRuntime $checkout): CheckoutSessionResource
    {
        $data = $request->validate([
            'approval_token' => ['required', 'string', 'min:32', 'max:255'],
            'expected_pricing_hash' => ['nullable', 'string', 'size:64'],
        ]);

        return new CheckoutSessionResource($checkout->approve(
            $this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkout),
            $data['approval_token'],
            $this->requiredIdempotencyKey($request),
            $data['expected_pricing_hash'] ?? null,
        ));
    }

    public function cancel(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $carts, CheckoutRuntime $checkout): CheckoutSessionResource
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        return new CheckoutSessionResource($checkout->cancel(
            $this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkout),
            $data['reason'] ?? null,
            $this->idempotencyKey($request),
        ));
    }

    private function checkoutFor(
        Chatbot $chatbot,
        string $sessionId,
        Request $request,
        CartRuntime $carts,
        CheckoutRuntime $checkout,
    ): CheckoutSession {
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

        return $checkout->getOrCreate($cart, $this->idempotencyKey($request));
    }

    private function idempotencyKey(Request $request): ?string
    {
        $value = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function requiredIdempotencyKey(Request $request): string
    {
        $key = $this->idempotencyKey($request);
        if ($key === null) {
            throw ValidationException::withMessages(['idempotency_key' => 'The Idempotency-Key header is required.']);
        }
        return $key;
    }
}
