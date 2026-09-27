<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\PaymentIntentResource;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentIntent;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAccount;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPayment;
use App\Extensions\ChatbotEcommerce\System\Services\CartRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CheckoutRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\PaymentRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\RentalHireRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PaymentApiController extends Controller
{
    public function createCheckout(
        Chatbot $chatbot,
        string $sessionId,
        Request $request,
        CartRuntime $carts,
        CheckoutRuntime $checkouts,
        PaymentRuntime $payments,
    ): PaymentIntentResource {
        $data = $request->validate([
            'provider' => ['nullable', 'string', 'max:100'],
            'method' => ['required', 'string', 'max:80'],
            'payment_url' => ['nullable', 'url', 'max:2048'],
            'instructions' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $checkout = $this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkouts);
        return new PaymentIntentResource($payments->createForCheckout($checkout, $data['method'], $this->requiredIdempotencyKey($request), $data));
    }

    public function showCheckout(Chatbot $chatbot, string $sessionId, PaymentIntent $intent): PaymentIntentResource
    {
        $checkout = $intent->checkout;
        if (! $checkout || (int) $checkout->chatbot_id !== (int) $chatbot->getAttribute('id') || (string) $checkout->session_id !== $sessionId) {
            abort(404);
        }
        return new PaymentIntentResource($intent->load(['checkout', 'refunds', 'bnplOffer.providerProfile']));
    }

    public function createRental(
        Chatbot $chatbot,
        RentalAccount $account,
        RentalPayment $payment,
        Request $request,
        RentalHireRuntime $rentalHire,
        PaymentRuntime $payments,
    ): PaymentIntentResource {
        $this->authorizeRentalAccount($chatbot, $account, $request, $rentalHire);
        if ((int) $payment->rental_account_id !== (int) $account->id) {
            abort(404);
        }
        $data = $request->validate([
            'provider' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'string', 'max:80'],
            'payment_url' => ['nullable', 'url', 'max:2048'],
            'instructions' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);
        return new PaymentIntentResource($payments->createForRentalPayment(
            $payment,
            $data['method'] ?? $payment->method,
            $this->requiredIdempotencyKey($request),
            $data,
        ));
    }

    public function showRental(
        Chatbot $chatbot,
        RentalAccount $account,
        RentalPayment $payment,
        PaymentIntent $intent,
        Request $request,
        RentalHireRuntime $rentalHire,
    ): PaymentIntentResource {
        $this->authorizeRentalAccount($chatbot, $account, $request, $rentalHire);
        if ((int) $payment->rental_account_id !== (int) $account->id || (int) $intent->rental_payment_id !== (int) $payment->id) {
            abort(404);
        }
        return new PaymentIntentResource($intent->load(['rentalPayment', 'refunds', 'bnplOffer.providerProfile']));
    }

    private function checkoutFor(Chatbot $chatbot, string $sessionId, Request $request, CartRuntime $carts, CheckoutRuntime $checkouts): CheckoutSession
    {
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
        return $checkouts->getOrCreate($cart, $request->header('Idempotency-Key'));
    }

    private function authorizeRentalAccount(Chatbot $chatbot, RentalAccount $account, Request $request, RentalHireRuntime $runtime): void
    {
        if ((int) $account->chatbot_id !== (int) $chatbot->getAttribute('id')) {
            abort(404);
        }
        $token = $request->header('X-Rental-Access-Token') ?: $request->input('access_token');
        if (! $runtime->authenticate($account, is_string($token) ? $token : null)) {
            abort(403, 'A valid rental or hire account access token is required.');
        }
    }

    private function requiredIdempotencyKey(Request $request): string
    {
        $key = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key')));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'The Idempotency-Key header is required.']);
        }
        return $key;
    }
}
