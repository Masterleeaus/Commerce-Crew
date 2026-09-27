<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\BnplOfferResource;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAccount;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPayment;
use App\Extensions\ChatbotEcommerce\System\Services\BnplRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CartRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CheckoutRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\RentalHireRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BnplApiController extends Controller
{
    public function checkoutOffers(
        Chatbot $chatbot,
        string $sessionId,
        Request $request,
        CartRuntime $carts,
        CheckoutRuntime $checkouts,
        BnplRuntime $bnpl,
    ): JsonResponse {
        $data = $request->validate([
            'country' => ['nullable', 'string', 'size:2'],
            'shopping_mode' => ['nullable', Rule::in(['native', 'marketplace_assisted'])],
        ]);
        $checkout = $this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkouts);

        return response()->json(['data' => $bnpl->offersForCheckout($checkout, $data)]);
    }

    public function selectCheckout(
        Chatbot $chatbot,
        string $sessionId,
        Request $request,
        CartRuntime $carts,
        CheckoutRuntime $checkouts,
        BnplRuntime $bnpl,
    ): BnplOfferResource {
        $data = $request->validate([
            'provider_code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/'],
            'installment_count' => ['required', 'integer', 'min:2', 'max:60'],
            'country' => ['nullable', 'string', 'size:2'],
            'shopping_mode' => ['nullable', Rule::in(['native', 'marketplace_assisted'])],
            'customer_accepts_provider_terms' => ['required', 'accepted'],
            'return_url' => ['nullable', 'url', 'max:2048'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $checkout = $this->checkoutFor($chatbot, $sessionId, $request, $carts, $checkouts);

        return new BnplOfferResource($bnpl->selectForCheckout(
            $checkout,
            (string) $data['provider_code'],
            (int) $data['installment_count'],
            $this->requiredIdempotencyKey($request),
            $data,
        ));
    }

    public function rentalOffers(
        Chatbot $chatbot,
        RentalAccount $account,
        RentalPayment $payment,
        Request $request,
        RentalHireRuntime $rentalHire,
        BnplRuntime $bnpl,
    ): JsonResponse {
        $this->authorizeRentalAccount($chatbot, $account, $request, $rentalHire);
        if ((int) $payment->rental_account_id !== (int) $account->id) { abort(404); }
        $data = $request->validate(['country' => ['nullable', 'string', 'size:2']]);

        return response()->json(['data' => $bnpl->offersForRentalPayment($payment, $account, $data)]);
    }

    public function selectRental(
        Chatbot $chatbot,
        RentalAccount $account,
        RentalPayment $payment,
        Request $request,
        RentalHireRuntime $rentalHire,
        BnplRuntime $bnpl,
    ): BnplOfferResource {
        $this->authorizeRentalAccount($chatbot, $account, $request, $rentalHire);
        if ((int) $payment->rental_account_id !== (int) $account->id) { abort(404); }
        $data = $request->validate([
            'provider_code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/'],
            'installment_count' => ['required', 'integer', 'min:2', 'max:60'],
            'country' => ['nullable', 'string', 'size:2'],
            'customer_accepts_provider_terms' => ['required', 'accepted'],
            'return_url' => ['nullable', 'url', 'max:2048'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return new BnplOfferResource($bnpl->selectForRentalPayment(
            $payment,
            $account,
            (string) $data['provider_code'],
            (int) $data['installment_count'],
            $this->requiredIdempotencyKey($request),
            $data,
        ));
    }

    private function checkoutFor(
        Chatbot $chatbot,
        string $sessionId,
        Request $request,
        CartRuntime $carts,
        CheckoutRuntime $checkouts,
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
