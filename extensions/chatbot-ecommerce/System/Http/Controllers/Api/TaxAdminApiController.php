<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\ChatbotEcommerce\System\Models\TaxExemption;
use App\Extensions\ChatbotEcommerce\System\Models\TaxRate;
use App\Extensions\ChatbotEcommerce\System\Models\TaxZone;
use App\Extensions\ChatbotEcommerce\System\Support\TaxCalculator;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TaxAdminApiController extends Controller
{
    public function zones(Request $request): JsonResponse
    {
        return response()->json(app(CommerceTenantRuntime::class)->scope(TaxZone::query(), $request)
            ->withCount('rates')
            ->when($request->integer('chatbot_id') > 0, fn ($query) => $query->where('chatbot_id', $request->integer('chatbot_id')))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderByDesc('priority')->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function storeZone(Request $request): JsonResponse
    {
        $payload = $this->zonePayload($request);
        $payload['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        return response()->json(['data' => TaxZone::query()->create($payload)], 201);
    }

    public function updateZone(Request $request, TaxZone $zone): JsonResponse
    {
        $zone->fill($this->zonePayload($request, true))->save();
        return response()->json(['data' => $zone->refresh()]);
    }

    public function destroyZone(TaxZone $zone): JsonResponse
    {
        $zone->forceFill(['active' => false])->save();
        $zone->rates()->update(['active' => false]);
        return response()->json(null, 204);
    }

    public function rates(Request $request): JsonResponse
    {
        return response()->json(app(CommerceTenantRuntime::class)->scope(TaxRate::query(), $request)->with('zone')
            ->when($request->integer('chatbot_id') > 0, fn ($query) => $query->where('chatbot_id', $request->integer('chatbot_id')))
            ->when($request->integer('tax_zone_id') > 0, fn ($query) => $query->where('tax_zone_id', $request->integer('tax_zone_id')))
            ->when($request->filled('tax_class'), fn ($query) => $query->where('tax_class', (string) $request->string('tax_class')))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderByDesc('priority')->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function storeRate(Request $request): JsonResponse
    {
        $payload = $this->ratePayload($request);
        $payload['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        $this->assertZoneTenant($request, $payload);
        return response()->json(['data' => TaxRate::query()->create($payload)], 201);
    }

    public function updateRate(Request $request, TaxRate $rate): JsonResponse
    {
        $payload = $this->ratePayload($request, true, $rate);
        $this->assertZoneTenant($request, $payload, $rate);
        $rate->fill($payload)->save();
        return response()->json(['data' => $rate->refresh()]);
    }

    public function destroyRate(TaxRate $rate): JsonResponse
    {
        $rate->forceFill(['active' => false])->save();
        return response()->json(null, 204);
    }

    public function exemptions(Request $request): JsonResponse
    {
        return response()->json(app(CommerceTenantRuntime::class)->scope(TaxExemption::query(), $request)
            ->when($request->integer('chatbot_id') > 0, fn ($query) => $query->where('chatbot_id', $request->integer('chatbot_id')))
            ->when($request->integer('customer_identity_id') > 0, fn ($query) => $query->where('customer_identity_id', $request->integer('customer_identity_id')))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function storeExemption(Request $request): JsonResponse
    {
        $payload = $this->exemptionPayload($request);
        $payload['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        return response()->json(['data' => TaxExemption::query()->create($payload)], 201);
    }

    public function updateExemption(Request $request, TaxExemption $exemption): JsonResponse
    {
        $exemption->fill($this->exemptionPayload($request, true, $exemption))->save();
        return response()->json(['data' => $exemption->refresh()]);
    }

    public function destroyExemption(TaxExemption $exemption): JsonResponse
    {
        $exemption->forceFill(['active' => false])->save();
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
            'prices_include_tax' => ['sometimes', 'nullable', 'boolean'],
            'countries' => ['sometimes', 'nullable', 'array'],
            'countries.*' => ['string', 'size:2'],
            'regions' => ['sometimes', 'nullable', 'array'],
            'regions.*' => ['string', 'max:120'],
            'postcode_patterns' => ['sometimes', 'nullable', 'array'],
            'postcode_patterns.*' => ['string', 'max:40'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
    }

    private function ratePayload(Request $request, bool $partial = false, ?TaxRate $existing = null): array
    {
        $required = $partial ? ['sometimes'] : ['required'];
        $data = $request->validate([
            'tax_zone_id' => ['sometimes', 'nullable', 'integer', 'exists:ext_chatbot_tax_zones,id'],
            'chatbot_id' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'code' => [...$required, 'string', 'max:100'],
            'name' => [...$required, 'string', 'max:191'],
            'tax_class' => ['sometimes', 'string', 'max:100'],
            'rate_bps' => [...$required, 'integer', 'min:0', 'max:' . TaxCalculator::MAX_RATE_BPS],
            'compound' => ['sometimes', 'boolean'],
            'applies_to_products' => ['sometimes', 'boolean'],
            'applies_to_shipping' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'between:-100000,100000'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $starts = $data['starts_at'] ?? $existing?->starts_at;
        $ends = $data['ends_at'] ?? $existing?->ends_at;
        if ($starts !== null && $ends !== null && strtotime((string) $ends) < strtotime((string) $starts)) {
            throw ValidationException::withMessages(['ends_at' => 'The tax rate end date must be after its start date.']);
        }
        if (isset($data['code'])) {
            $data['code'] = strtolower(trim((string) $data['code']));
        }
        if (isset($data['tax_class'])) {
            $data['tax_class'] = strtolower(trim((string) $data['tax_class'])) ?: 'default';
        }

        return $data;
    }

    private function exemptionPayload(Request $request, bool $partial = false, ?TaxExemption $existing = null): array
    {
        $data = $request->validate([
            'chatbot_id' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'customer_identity_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'exemption_key' => ['sometimes', 'nullable', 'string', 'max:191', Rule::unique('ext_chatbot_tax_exemptions', 'exemption_key')->ignore($existing?->id)],
            'tax_class' => ['sometimes', 'nullable', 'string', 'max:100'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
        if (! $partial && ! isset($data['customer_identity_id']) && empty($data['exemption_key'])) {
            throw ValidationException::withMessages(['exemption' => 'A customer identity or exemption key is required.']);
        }
        if (isset($data['tax_class'])) {
            $data['tax_class'] = ($value = strtolower(trim((string) $data['tax_class']))) !== '' ? $value : null;
        }

        return $data;
    }
    private function assertZoneTenant(Request $request, array $payload, ?TaxRate $existing = null): void
    {
        $chatbotId = (int) ($payload['chatbot_id'] ?? $existing?->chatbot_id ?? 0);
        if ($chatbotId <= 0) {
            $chatbotId = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        } elseif (! in_array($chatbotId, app(CommerceTenantRuntime::class)->ownedChatbotIds($request), true)) {
            abort(404);
        }
        $zoneId = (int) ($payload['tax_zone_id'] ?? $existing?->tax_zone_id ?? 0);
        if ($zoneId > 0 && ! TaxZone::query()->whereKey($zoneId)->where('chatbot_id', $chatbotId)->exists()) {
            throw ValidationException::withMessages(['tax_zone_id' => 'The selected tax zone does not belong to this chatbot.']);
        }
    }

}
