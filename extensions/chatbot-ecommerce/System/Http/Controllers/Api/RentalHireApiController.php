<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\RentalPaymentResource;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\RentalReceiptResource;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAccount;
use App\Extensions\ChatbotEcommerce\System\Models\RentalReceipt;
use App\Extensions\ChatbotEcommerce\System\Services\RentalHireRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RentalHireApiController extends Controller
{
    public function summary(Chatbot $chatbot, RentalAccount $account, Request $request, RentalHireRuntime $runtime): JsonResponse
    {
        $this->authorizeAccount($chatbot, $account, $request, $runtime);
        return response()->json(['data' => $runtime->accountSummary($account)]);
    }

    public function ledger(Chatbot $chatbot, RentalAccount $account, Request $request, RentalHireRuntime $runtime): JsonResponse
    {
        $this->authorizeAccount($chatbot, $account, $request, $runtime);
        return response()->json(['data' => $runtime->ledger($account, $request->integer('limit', 200))]);
    }

    public function paymentRequest(Chatbot $chatbot, RentalAccount $account, Request $request, RentalHireRuntime $runtime): RentalPaymentResource
    {
        $this->authorizeAccount($chatbot, $account, $request, $runtime);
        $data = $request->validate([
            'amount' => ['nullable', 'integer', 'min:1'],
            'method' => ['required', 'string', 'max:60'],
            'provider' => ['nullable', 'string', 'max:100'],
            'provider_payment_id' => ['nullable', 'string', 'max:191'],
            'payment_url' => ['nullable', 'url', 'max:2048'],
            'instructions' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $summary = $runtime->accountSummary($account);
        $amount = (int) ($data['amount'] ?? ($summary['next_due']['balance_due'] ?? $summary['outstanding_balance'] ?? 0));
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'There is no positive balance to pay.']);
        }
        $payment = $runtime->createPaymentRequest($account, $amount, $data['method'], $this->requiredIdempotencyKey($request), $data);
        return new RentalPaymentResource($payment->load(['allocations.charge', 'receipt']));
    }

    public function receipt(Chatbot $chatbot, RentalAccount $account, RentalReceipt $receipt, Request $request, RentalHireRuntime $runtime): RentalReceiptResource
    {
        $this->authorizeAccount($chatbot, $account, $request, $runtime);
        if ((int) $receipt->rental_account_id !== (int) $account->id) {
            abort(404);
        }
        return new RentalReceiptResource($receipt);
    }

    private function authorizeAccount(Chatbot $chatbot, RentalAccount $account, Request $request, RentalHireRuntime $runtime): void
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
