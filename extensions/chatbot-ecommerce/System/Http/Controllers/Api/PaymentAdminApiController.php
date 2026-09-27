<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\PaymentIntentResource;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentIntent;
use App\Extensions\ChatbotEcommerce\System\Services\PaymentRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PaymentAdminApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = app(CommerceTenantRuntime::class)->scope(PaymentIntent::query(), $request)->with(['checkout', 'rentalPayment', 'refunds', 'bnplOffer.providerProfile'])->latest('id');
        foreach (['status', 'provider', 'method'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->toString());
            }
        }
        if ($request->filled('scope')) {
            $request->string('scope')->toString() === 'checkout'
                ? $query->whereNotNull('checkout_session_id')
                : $query->whereNotNull('rental_payment_id');
        }
        return response()->json(PaymentIntentResource::collection($query->paginate(min(max($request->integer('per_page', 25), 1), 100)))->response()->getData(true));
    }

    public function show(PaymentIntent $intent): PaymentIntentResource
    {
        return new PaymentIntentResource($intent->load(['checkout', 'rentalPayment', 'refunds', 'operations', 'bnplOffer.providerProfile']));
    }

    public function authorizePayment(PaymentIntent $intent, Request $request, PaymentRuntime $payments): PaymentIntentResource
    {
        $data = $request->validate(['amount' => ['nullable', 'integer', 'min:1']]);
        return new PaymentIntentResource($payments->authorize($intent, isset($data['amount']) ? (int) $data['amount'] : null, $this->requiredIdempotencyKey($request)));
    }

    public function capture(PaymentIntent $intent, Request $request, PaymentRuntime $payments): PaymentIntentResource
    {
        $data = $request->validate(['amount' => ['nullable', 'integer', 'min:1']]);
        return new PaymentIntentResource($payments->capture($intent, isset($data['amount']) ? (int) $data['amount'] : null, $this->requiredIdempotencyKey($request)));
    }

    public function cancel(PaymentIntent $intent, Request $request, PaymentRuntime $payments): PaymentIntentResource
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        return new PaymentIntentResource($payments->cancel($intent, $this->requiredIdempotencyKey($request), $data['reason'] ?? null));
    }

    public function refund(PaymentIntent $intent, Request $request, PaymentRuntime $payments): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $refund = $payments->refund($intent, (int) $data['amount'], $this->requiredIdempotencyKey($request), $data['reason'] ?? null);
        return response()->json(['data' => [
            'uuid' => $refund->uuid,
            'payment_intent_uuid' => $refund->intent?->uuid,
            'amount' => (int) $refund->amount,
            'currency' => $refund->currency,
            'status' => $refund->status,
            'reason' => $refund->reason,
            'processed_at' => $refund->processed_at?->toIso8601String(),
        ]], 201);
    }

    public function reconcile(PaymentIntent $intent, Request $request, PaymentRuntime $payments): PaymentIntentResource
    {
        return new PaymentIntentResource($payments->reconcile($intent, $this->requiredIdempotencyKey($request)));
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
