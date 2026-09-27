<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\Fulfillment;
use App\Extensions\ChatbotEcommerce\System\Services\FulfillmentRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class FulfillmentApiController extends Controller
{
    public function index(CheckoutSession $checkout, FulfillmentRuntime $runtime): JsonResponse
    {
        return response()->json([
            'data' => $checkout->fulfillments()->with('items')->orderByDesc('id')->get()->map(fn ($fulfillment) => $this->payload($fulfillment))->values(),
            'summary' => $runtime->summaryForCheckout($checkout),
        ]);
    }

    public function store(CheckoutSession $checkout, Request $request, FulfillmentRuntime $runtime): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.cart_line_uuid' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'items.*.metadata' => ['sometimes', 'array'],
            'method_type' => ['sometimes', 'in:delivery,pickup,digital'],
            'location_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'carrier' => ['sometimes', 'nullable', 'string', 'max:120'],
            'service' => ['sometimes', 'nullable', 'string', 'max:120'],
            'customer_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $fulfillment = $runtime->create($checkout, $data['items'], $this->requiredIdempotencyKey($request), $data);
        return response()->json(['data' => $this->payload($fulfillment)], 201);
    }

    public function processing(Fulfillment $fulfillment, FulfillmentRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $this->payload($runtime->markProcessing($fulfillment))]);
    }

    public function shipped(Fulfillment $fulfillment, Request $request, FulfillmentRuntime $runtime): JsonResponse
    {
        $data = $request->validate([
            'carrier' => ['sometimes', 'nullable', 'string', 'max:120'],
            'service' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:191'],
            'tracking_url' => ['sometimes', 'nullable', 'url', 'max:2000'],
        ]);
        return response()->json(['data' => $this->payload($runtime->markShipped($fulfillment, $data))]);
    }

    public function delivered(Fulfillment $fulfillment, FulfillmentRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $this->payload($runtime->markDelivered($fulfillment))]);
    }

    public function cancel(Fulfillment $fulfillment, Request $request, FulfillmentRuntime $runtime): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:5000']]);
        return response()->json(['data' => $this->payload($runtime->cancel($fulfillment, $data['reason'] ?? null))]);
    }

    private function requiredIdempotencyKey(Request $request): string
    {
        $value = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'The Idempotency-Key header is required.']);
        }
        return trim($value);
    }

    /** @return array<string, mixed> */
    private function payload(Fulfillment $fulfillment): array
    {
        $fulfillment->loadMissing('items');
        return [
            'uuid' => $fulfillment->uuid,
            'checkout_uuid' => $fulfillment->checkout?->uuid,
            'order_reference' => $fulfillment->order_reference,
            'status' => $fulfillment->status,
            'method_type' => $fulfillment->method_type,
            'location_id' => $fulfillment->location_id,
            'carrier' => $fulfillment->carrier,
            'service' => $fulfillment->service,
            'tracking_number' => $fulfillment->tracking_number,
            'tracking_url' => $fulfillment->tracking_url,
            'items' => $fulfillment->items->map(fn ($item): array => [
                'cart_line_id' => $item->cart_line_id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => (int) $item->quantity,
            ])->values(),
            'processing_at' => $fulfillment->processing_at?->toIso8601String(),
            'shipped_at' => $fulfillment->shipped_at?->toIso8601String(),
            'delivered_at' => $fulfillment->delivered_at?->toIso8601String(),
            'cancelled_at' => $fulfillment->cancelled_at?->toIso8601String(),
            'created_at' => $fulfillment->created_at?->toIso8601String(),
            'updated_at' => $fulfillment->updated_at?->toIso8601String(),
        ];
    }
}
