<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\RentalAccountResource;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\RentalAgreementResource;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\RentalPaymentResource;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\RentalReceiptResource;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAccount;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAgreement;
use App\Extensions\ChatbotEcommerce\System\Models\RentalCharge;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPayment;
use App\Extensions\ChatbotEcommerce\System\Models\RentalReceipt;
use App\Extensions\ChatbotEcommerce\System\Services\RentalHireRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantRuntime;
use App\Extensions\ChatbotEcommerce\System\Support\RentalBillingSchedule;
use App\Extensions\ChatbotEcommerce\System\Support\RentalHireStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RentalHireAdminApiController extends Controller
{
    public function accounts(Request $request): JsonResponse
    {
        $accounts = app(CommerceTenantRuntime::class)->scope(RentalAccount::query(), $request)
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('account_type'), fn ($query) => $query->where('account_type', (string) $request->string('account_type')))
            ->when($request->integer('chatbot_id') > 0, fn ($query) => $query->where('chatbot_id', $request->integer('chatbot_id')))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('display_name', 'like', '%' . (string) $request->string('q') . '%')
                ->orWhere('account_number', 'like', '%' . (string) $request->string('q') . '%')))
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));
        return response()->json($accounts);
    }

    public function storeAccount(Request $request, RentalHireRuntime $runtime): JsonResponse
    {
        $data = $request->validate([
            'chatbot_id' => ['required', 'integer', 'min:1'],
            'customer_identity_id' => ['nullable', 'integer', 'min:1'],
            'account_number' => ['nullable', 'string', 'max:100', 'unique:ext_chatbot_rental_accounts,account_number'],
            'account_type' => ['required', Rule::in(['rent', 'hire', 'lease', 'other'])],
            'display_name' => ['required', 'string', 'max:191'],
            'currency' => ['nullable', 'string', 'size:3'],
            'contact_email' => ['nullable', 'email:rfc', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $data['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        $result = $runtime->createAccount($data);
        return response()->json([
            'data' => (new RentalAccountResource($result['account']))->resolve($request),
            'access_token' => $result['access_token'],
        ], 201);
    }

    public function showAccount(RentalAccount $account, RentalHireRuntime $runtime): JsonResponse
    {
        return response()->json([
            'data' => new RentalAccountResource($account),
            'summary' => $runtime->accountSummary($account),
        ]);
    }

    public function rotateToken(RentalAccount $account, RentalHireRuntime $runtime): JsonResponse
    {
        $result = $runtime->rotateAccessToken($account);
        return response()->json(['data' => new RentalAccountResource($result['account']), 'access_token' => $result['access_token']]);
    }

    public function agreements(RentalAccount $account, Request $request): JsonResponse
    {
        return response()->json($account->agreements()->with('rates')->orderByDesc('id')->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function storeAgreement(RentalAccount $account, Request $request, RentalHireRuntime $runtime): RentalAgreementResource
    {
        $data = $request->validate([
            'agreement_number' => ['nullable', 'string', 'max:100', 'unique:ext_chatbot_rental_agreements,agreement_number'],
            'agreement_type' => ['required', Rule::in(['rent', 'hire', 'lease', 'other'])],
            'subject_type' => ['nullable', 'string', 'max:80'],
            'subject_reference' => ['nullable', 'string', 'max:191'],
            'subject_description' => ['nullable', 'string', 'max:5000'],
            'billing_frequency' => ['required', Rule::in(RentalBillingSchedule::FREQUENCIES)],
            'billing_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'custom_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_if:billing_frequency,custom'],
            'charge_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'due_offset_days' => ['nullable', 'integer', 'between:-365,365'],
            'status' => ['nullable', Rule::in([RentalHireStatus::AGREEMENT_DRAFT, RentalHireStatus::AGREEMENT_ACTIVE])],
            'allow_partial_payments' => ['nullable', 'boolean'],
            'auto_allocate_payments' => ['nullable', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);
        return new RentalAgreementResource($runtime->createAgreement($account, $data)->load(['account', 'rates']));
    }

    public function updateAgreement(RentalAgreement $agreement, Request $request): RentalAgreementResource
    {
        $data = $request->validate([
            'subject_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'subject_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'subject_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ends_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:' . $agreement->starts_on?->toDateString()],
            'due_offset_days' => ['sometimes', 'integer', 'between:-365,365'],
            'status' => ['sometimes', Rule::in([RentalHireStatus::AGREEMENT_DRAFT, RentalHireStatus::AGREEMENT_ACTIVE, RentalHireStatus::AGREEMENT_SUSPENDED, RentalHireStatus::AGREEMENT_ENDED, RentalHireStatus::AGREEMENT_CANCELLED])],
            'allow_partial_payments' => ['sometimes', 'boolean'],
            'auto_allocate_payments' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);
        if (($data['status'] ?? null) === RentalHireStatus::AGREEMENT_ACTIVE && ! $agreement->activated_at) {
            $data['activated_at'] = now();
        }
        if (($data['status'] ?? null) === RentalHireStatus::AGREEMENT_ENDED) {
            $data['ended_at'] = now();
            $data['next_charge_date'] = null;
        }
        if (($data['status'] ?? null) === RentalHireStatus::AGREEMENT_CANCELLED) {
            $data['cancelled_at'] = now();
            $data['next_charge_date'] = null;
        }
        $agreement->fill($data)->save();
        return new RentalAgreementResource($agreement->refresh()->load(['account', 'rates']));
    }

    public function addRate(RentalAgreement $agreement, Request $request, RentalHireRuntime $runtime): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
            'metadata' => ['sometimes', 'array'],
        ]);
        return response()->json(['data' => $runtime->addRate($agreement, (int) $data['amount'], $data['effective_from'], $data['reason'] ?? null, $data['metadata'] ?? [])], 201);
    }

    public function generateCharges(RentalAgreement $agreement, Request $request, RentalHireRuntime $runtime): JsonResponse
    {
        $data = $request->validate(['through_date' => ['nullable', 'date_format:Y-m-d']]);
        $charges = $runtime->generateCharges($agreement, $data['through_date'] ?? null);
        return response()->json(['data' => $charges, 'generated_count' => $charges->count()]);
    }

    public function summary(RentalAccount $account, RentalHireRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $runtime->accountSummary($account)]);
    }

    public function ledger(RentalAccount $account, Request $request, RentalHireRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $runtime->ledger($account, $request->integer('limit', 500))]);
    }

    public function paymentRequest(RentalAccount $account, Request $request, RentalHireRuntime $runtime): RentalPaymentResource
    {
        $data = $this->validatedPayment($request);
        return new RentalPaymentResource($runtime->createPaymentRequest($account, (int) $data['amount'], $data['method'], $this->requiredIdempotencyKey($request), $data));
    }

    public function recordPayment(RentalAccount $account, Request $request, RentalHireRuntime $runtime): RentalPaymentResource
    {
        $data = $this->validatedPayment($request);
        $payment = $runtime->createPaymentRequest($account, (int) $data['amount'], $data['method'], $this->requiredIdempotencyKey($request), $data);
        return new RentalPaymentResource($runtime->confirmPayment($payment, $this->requiredConfirmationKey($request), $data));
    }

    public function confirmPayment(RentalPayment $payment, Request $request, RentalHireRuntime $runtime): RentalPaymentResource
    {
        $data = $request->validate([
            'provider' => ['nullable', 'string', 'max:100'],
            'provider_payment_id' => ['nullable', 'string', 'max:191'],
            'received_at' => ['nullable', 'date'],
            'auto_allocate' => ['nullable', 'boolean'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.charge_uuid' => ['required_with:allocations', 'uuid'],
            'allocations.*.amount' => ['required_with:allocations', 'integer', 'min:1'],
        ]);
        return new RentalPaymentResource($runtime->confirmPayment($payment, $this->requiredIdempotencyKey($request), $data));
    }

    public function allocatePayment(RentalPayment $payment, Request $request, RentalHireRuntime $runtime): RentalPaymentResource
    {
        $data = $request->validate([
            'allocations' => ['nullable', 'array'],
            'allocations.*.charge_uuid' => ['required_with:allocations', 'uuid'],
            'allocations.*.amount' => ['required_with:allocations', 'integer', 'min:1'],
        ]);
        return new RentalPaymentResource($runtime->allocatePayment($payment, $data['allocations'] ?? null, $this->requiredIdempotencyKey($request)));
    }

    public function reversePayment(RentalPayment $payment, Request $request, RentalHireRuntime $runtime): RentalPaymentResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        return new RentalPaymentResource($runtime->reversePayment($payment, $data['reason'], $this->requiredIdempotencyKey($request)));
    }

    public function adjustment(RentalAccount $account, Request $request, RentalHireRuntime $runtime): JsonResponse
    {
        $data = $request->validate([
            'charge_uuid' => ['nullable', 'uuid'],
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $charge = isset($data['charge_uuid']) ? RentalCharge::query()->where('uuid', $data['charge_uuid'])->firstOrFail() : null;
        return response()->json(['data' => $runtime->createAdjustment($account, $data['direction'], (int) $data['amount'], $data['reason'], $charge, $this->requiredIdempotencyKey($request), $data['metadata'] ?? [])], 201);
    }

    public function receipt(RentalReceipt $receipt): RentalReceiptResource
    {
        return new RentalReceiptResource($receipt);
    }

    private function validatedPayment(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'max:60'],
            'provider' => ['nullable', 'string', 'max:100'],
            'provider_payment_id' => ['nullable', 'string', 'max:191'],
            'payment_url' => ['nullable', 'url', 'max:2048'],
            'received_at' => ['nullable', 'date'],
            'auto_allocate' => ['nullable', 'boolean'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.charge_uuid' => ['required_with:allocations', 'uuid'],
            'allocations.*.amount' => ['required_with:allocations', 'integer', 'min:1'],
            'instructions' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);
    }

    private function requiredIdempotencyKey(Request $request): string
    {
        $key = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key')));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'The Idempotency-Key header is required.']);
        }
        return $key;
    }

    private function requiredConfirmationKey(Request $request): string
    {
        $key = trim((string) ($request->header('Confirmation-Idempotency-Key') ?: $request->input('confirmation_idempotency_key')));
        return $key !== '' ? $key : $this->requiredIdempotencyKey($request) . ':confirm';
    }
}
