<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\Coupon;
use App\Extensions\ChatbotEcommerce\System\Models\PricingRule;
use App\Extensions\ChatbotEcommerce\System\Models\PricingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PricingAdminApiController extends Controller
{
    public function rules(Request $request): JsonResponse
    {
        $tenants = app(CommerceTenantRuntime::class);
        $rules = $tenants->scope(PricingRule::query(), $request)
            ->when($request->filled('rule_type'), fn ($query) => $query->where('rule_type', (string) $request->string('rule_type')))
            ->when($request->filled('scope'), fn ($query) => $query->where('scope', (string) $request->string('scope')))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->when($request->integer('chatbot_id') > 0, fn ($query) => $query->where('chatbot_id', $request->integer('chatbot_id')))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json($rules);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $payload = $this->validatedRule($request);
        $payload['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        $rule = PricingRule::query()->create($payload);

        return response()->json(['data' => $rule], 201);
    }

    public function updateRule(Request $request, PricingRule $rule): JsonResponse
    {
        $rule->fill($this->validatedRule($request, true, $rule))->save();

        return response()->json(['data' => $rule->refresh()]);
    }

    public function destroyRule(PricingRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(null, 204);
    }

    public function coupons(Request $request): JsonResponse
    {
        $tenants = app(CommerceTenantRuntime::class);
        $coupons = $tenants->scope(Coupon::query(), $request)
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->when($request->filled('q'), fn ($query) => $query->where('code', 'like', '%' . (string) $request->string('q') . '%'))
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json($coupons);
    }

    public function storeCoupon(Request $request): JsonResponse
    {
        $payload = $this->validatedCoupon($request);
        $payload['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        $payload['code'] = strtoupper(trim((string) $payload['code']));
        $coupon = Coupon::query()->create($payload);

        return response()->json(['data' => $coupon], 201);
    }

    public function updateCoupon(Request $request, Coupon $coupon): JsonResponse
    {
        $payload = $this->validatedCoupon($request, true, $coupon);
        if (isset($payload['code'])) {
            $payload['code'] = strtoupper(trim((string) $payload['code']));
        }
        $coupon->fill($payload)->save();

        return response()->json(['data' => $coupon->refresh()]);
    }

    public function destroyCoupon(Coupon $coupon): JsonResponse
    {
        $coupon->forceFill(['active' => false])->save();

        return response()->json(null, 204);
    }

    public function snapshots(ChatbotCart $cart, Request $request): JsonResponse
    {
        $snapshots = PricingSnapshot::query()
            ->where('cart_id', $cart->id)
            ->orderByDesc('cart_version')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json($snapshots);
    }

    private function validatedRule(Request $request, bool $partial = false, ?PricingRule $existing = null): array
    {
        $sometimes = $partial ? ['sometimes'] : ['required'];

        $data = $request->validate([
            'chatbot_id' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'name' => [...$sometimes, 'string', 'max:191'],
            'rule_type' => [...$sometimes, Rule::in(['price_override', 'percentage_discount', 'fixed_discount', 'free_shipping'])],
            'scope' => [...$sometimes, Rule::in(['line', 'cart', 'shipping'])],
            'target_type' => ['sometimes', Rule::in(['all', 'product', 'variant', 'category'])],
            'target_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'priority' => ['sometimes', 'integer', 'between:-100000,100000'],
            'stackable' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'value' => [...$sometimes, 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'min_quantity' => ['sometimes', 'integer', 'min:1', 'max:999999'],
            'min_subtotal' => ['sometimes', 'integer', 'min:0'],
            'channel' => ['sometimes', 'nullable', 'string', 'max:40'],
            'customer_identity_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'conditions' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $ruleType = (string) ($data['rule_type'] ?? $existing?->rule_type ?? '');
        $scope = (string) ($data['scope'] ?? $existing?->scope ?? 'line');
        $targetType = (string) ($data['target_type'] ?? $existing?->target_type ?? 'all');
        $targetId = $data['target_id'] ?? $existing?->target_id;
        $value = (int) ($data['value'] ?? $existing?->value ?? 0);
        $startsAt = $data['starts_at'] ?? $existing?->starts_at;
        $endsAt = $data['ends_at'] ?? $existing?->ends_at;

        if ($ruleType === 'price_override' && $scope !== 'line') {
            throw ValidationException::withMessages(['scope' => 'Price overrides must use line scope.']);
        }
        if ($ruleType === 'free_shipping' && $scope !== 'shipping') {
            throw ValidationException::withMessages(['scope' => 'Free-shipping rules must use shipping scope.']);
        }
        if ($ruleType === 'percentage_discount' && $value > 10000) {
            throw ValidationException::withMessages(['value' => 'Percentage discount values cannot exceed 10000 basis points.']);
        }
        if ($targetType !== 'all' && ! $targetId) {
            throw ValidationException::withMessages(['target_id' => 'A target ID is required for targeted pricing rules.']);
        }
        if ($startsAt && $endsAt && strtotime((string) $endsAt) < strtotime((string) $startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'The end date must be after or equal to the start date.']);
        }
        if (isset($data['currency']) && $data['currency'] !== null) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        return $data;
    }

    private function validatedCoupon(Request $request, bool $partial = false, ?Coupon $coupon = null): array
    {
        $sometimes = $partial ? ['sometimes'] : ['required'];
        $unique = Rule::unique('ext_chatbot_coupons', 'code');
        if ($coupon) {
            $unique->ignore($coupon->id);
        }

        $data = $request->validate([
            'chatbot_id' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'code' => [...$sometimes, 'string', 'max:100', $unique],
            'type' => [...$sometimes, Rule::in(['fixed', 'percentage', 'free_shipping'])],
            'value' => [...$sometimes, 'integer', 'min:0'],
            'minimum_amount' => ['sometimes', 'integer', 'min:0'],
            'max_discount_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'first_order_only' => ['sometimes', 'boolean'],
            'stackable' => ['sometimes', 'boolean'],
            'applies_to' => ['sometimes', Rule::in(['cart', 'products', 'variants', 'categories'])],
            'target_ids' => ['sometimes', 'nullable', 'array'],
            'target_ids.*' => ['integer', 'min:1'],
            'channels' => ['sometimes', 'nullable', 'array'],
            'channels.*' => ['string', 'max:40'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $type = (string) ($data['type'] ?? $coupon?->type ?? '');
        $value = (int) ($data['value'] ?? $coupon?->value ?? 0);
        $appliesTo = (string) ($data['applies_to'] ?? $coupon?->applies_to ?? 'cart');
        $targetIds = $data['target_ids'] ?? $coupon?->target_ids ?? [];
        $startsAt = $data['starts_at'] ?? $coupon?->starts_at;
        $endsAt = $data['ends_at'] ?? $coupon?->ends_at;

        if ($type === 'percentage' && $value > 10000) {
            throw ValidationException::withMessages(['value' => 'Percentage coupon values cannot exceed 10000 basis points.']);
        }
        if ($appliesTo !== 'cart' && $targetIds === []) {
            throw ValidationException::withMessages(['target_ids' => 'Target IDs are required for targeted coupons.']);
        }
        if ($startsAt && $endsAt && strtotime((string) $endsAt) < strtotime((string) $startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'The end date must be after or equal to the start date.']);
        }
        if (isset($data['currency']) && $data['currency'] !== null) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        return $data;
    }
}
