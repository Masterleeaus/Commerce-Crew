<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\ChatbotEcommerce\System\Models\ShippingMethod;
use App\Extensions\ChatbotEcommerce\System\Models\ShippingZone;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ShippingAdminApiController extends Controller
{
    public function zones(Request $request): JsonResponse
    {
        return response()->json(app(CommerceTenantRuntime::class)->scope(ShippingZone::query(), $request)
            ->withCount('methods')
            ->when($request->integer('chatbot_id') > 0, fn ($query) => $query->where('chatbot_id', $request->integer('chatbot_id')))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderByDesc('priority')->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function storeZone(Request $request): JsonResponse
    {
        $payload = $this->zonePayload($request);
        $payload['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        return response()->json(['data' => ShippingZone::query()->create($payload)], 201);
    }

    public function updateZone(Request $request, ShippingZone $zone): JsonResponse
    {
        $zone->fill($this->zonePayload($request, true))->save();
        return response()->json(['data' => $zone->refresh()]);
    }

    public function destroyZone(ShippingZone $zone): JsonResponse
    {
        $zone->forceFill(['active' => false])->save();
        $zone->methods()->update(['active' => false]);
        return response()->json(null, 204);
    }

    public function methods(Request $request): JsonResponse
    {
        return response()->json(app(CommerceTenantRuntime::class)->scope(ShippingMethod::query(), $request)->with('zone')
            ->when($request->integer('chatbot_id') > 0, fn ($query) => $query->where('chatbot_id', $request->integer('chatbot_id')))
            ->when($request->integer('shipping_zone_id') > 0, fn ($query) => $query->where('shipping_zone_id', $request->integer('shipping_zone_id')))
            ->when($request->filled('method_type'), fn ($query) => $query->where('method_type', (string) $request->string('method_type')))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderByDesc('priority')->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function storeMethod(Request $request): JsonResponse
    {
        $payload = $this->methodPayload($request);
        $payload['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        $this->assertZoneTenant($request, $payload);
        return response()->json(['data' => ShippingMethod::query()->create($payload)], 201);
    }

    public function updateMethod(Request $request, ShippingMethod $method): JsonResponse
    {
        $payload = $this->methodPayload($request, true, $method);
        $this->assertZoneTenant($request, $payload, $method);
        $method->fill($payload)->save();
        return response()->json(['data' => $method->refresh()]);
    }

    public function destroyMethod(ShippingMethod $method): JsonResponse
    {
        $method->forceFill(['active' => false])->save();
        return response()->json(null, 204);
    }

    private function zonePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? ['sometimes'] : ['required'];
        return $request->validate([
            'chatbot_id' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'name' => [...$required, 'string', 'max:191'],
            'priority' => ['sometimes', 'integer', 'between:-100000,100000'],
            'active' => ['sometimes', 'boolean'],
            'countries' => ['sometimes', 'nullable', 'array'],
            'countries.*' => ['string', 'size:2'],
            'regions' => ['sometimes', 'nullable', 'array'],
            'regions.*' => ['string', 'max:120'],
            'postcode_patterns' => ['sometimes', 'nullable', 'array'],
            'postcode_patterns.*' => ['string', 'max:40'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
    }

    private function methodPayload(Request $request, bool $partial = false, ?ShippingMethod $existing = null): array
    {
        $required = $partial ? ['sometimes'] : ['required'];
        $data = $request->validate([
            'shipping_zone_id' => ['sometimes', 'nullable', 'integer', 'exists:ext_chatbot_shipping_zones,id'],
            'chatbot_id' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'code' => [...$required, 'string', 'max:100'],
            'name' => [...$required, 'string', 'max:191'],
            'method_type' => [...$required, Rule::in(['delivery', 'pickup', 'digital'])],
            'rate_type' => [...$required, Rule::in(['flat', 'weight', 'subtotal', 'free'])],
            'amount' => ['sometimes', 'integer', 'min:0'],
            'base_amount' => ['sometimes', 'integer', 'min:0'],
            'per_kg_amount' => ['sometimes', 'integer', 'min:0'],
            'free_above' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'minimum_subtotal' => ['sometimes', 'integer', 'min:0'],
            'maximum_subtotal' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minimum_weight_grams' => ['sometimes', 'integer', 'min:0'],
            'maximum_weight_grams' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'estimated_days_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'estimated_days_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'priority' => ['sometimes', 'integer', 'between:-100000,100000'],
            'active' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $type = (string) ($data['method_type'] ?? $existing?->method_type ?? 'delivery');
        $zoneId = $data['shipping_zone_id'] ?? $existing?->shipping_zone_id;
        if ($type === 'delivery' && ! $zoneId) {
            throw ValidationException::withMessages(['shipping_zone_id' => 'Delivery methods require a shipping zone.']);
        }
        if (in_array($type, ['pickup', 'digital'], true)) {
            $data['shipping_zone_id'] = null;
            $data['amount'] = 0;
        }
        $minDays = $data['estimated_days_min'] ?? $existing?->estimated_days_min;
        $maxDays = $data['estimated_days_max'] ?? $existing?->estimated_days_max;
        if ($minDays !== null && $maxDays !== null && (int) $maxDays < (int) $minDays) {
            throw ValidationException::withMessages(['estimated_days_max' => 'Maximum delivery days must be greater than or equal to minimum delivery days.']);
        }
        $minSubtotal = $data['minimum_subtotal'] ?? $existing?->minimum_subtotal;
        $maxSubtotal = $data['maximum_subtotal'] ?? $existing?->maximum_subtotal;
        if ($maxSubtotal !== null && (int) $maxSubtotal < (int) $minSubtotal) {
            throw ValidationException::withMessages(['maximum_subtotal' => 'Maximum subtotal must be greater than or equal to minimum subtotal.']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }
        if (isset($data['code'])) {
            $data['code'] = strtolower(trim((string) $data['code']));
        }

        return $data;
    }
    private function assertZoneTenant(Request $request, array $payload, ?ShippingMethod $existing = null): void
    {
        $chatbotId = (int) ($payload['chatbot_id'] ?? $existing?->chatbot_id ?? 0);
        if ($chatbotId <= 0) {
            $chatbotId = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        } elseif (! in_array($chatbotId, app(CommerceTenantRuntime::class)->ownedChatbotIds($request), true)) {
            abort(404);
        }
        $zoneId = (int) ($payload['shipping_zone_id'] ?? $existing?->shipping_zone_id ?? 0);
        if ($zoneId > 0 && ! ShippingZone::query()->whereKey($zoneId)->where('chatbot_id', $chatbotId)->exists()) {
            throw ValidationException::withMessages(['shipping_zone_id' => 'The selected shipping zone does not belong to this chatbot.']);
        }
    }

}
