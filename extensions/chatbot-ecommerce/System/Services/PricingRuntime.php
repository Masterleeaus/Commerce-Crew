<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\Coupon;
use App\Extensions\ChatbotEcommerce\System\Models\PricingRule;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;
use App\Extensions\ChatbotEcommerce\System\Support\CartLineKey;
use App\Extensions\ChatbotEcommerce\System\Support\CartLineQuantity;
use App\Extensions\ChatbotEcommerce\System\Support\DiscountAllocator;
use App\Extensions\ChatbotEcommerce\System\Support\MoneyMath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PricingRuntime
{
    public const CALCULATION_VERSION = '3';

    public function __construct(
        private readonly CouponRuntime $coupons,
        private readonly TaxRuntime $taxes,
    ) {}

    /**
     * @param list<array<string, mixed>> $lines
     * @return array<string, mixed>
     */
    public function quote(
        array $lines,
        ?string $couponCode = null,
        bool $strictCoupon = false,
        array $context = [],
    ): array {
        $normalised = $this->normaliseLines($lines, $context);
        $currency = $normalised[0]['currency'] ?? strtoupper((string) ($context['currency'] ?? 'USD'));
        $context['currency'] = $currency;
        $rules = $this->activeRules($context);
        $listSubtotal = array_sum(array_column($normalised, 'list_total'));

        $normalised = $this->applyPriceOverrides($normalised, $rules, $context, $listSubtotal);
        $subtotal = array_sum(array_column($normalised, 'gross_total'));
        $coupon = $this->coupons->resolve($couponCode, $subtotal, $normalised, $context, $strictCoupon);
        $applyAutomaticDiscounts = ! $coupon || (bool) $coupon->stackable;
        if ($applyAutomaticDiscounts) {
            $normalised = $this->applyLineDiscountRules($normalised, $rules, $context, $subtotal);
            $normalised = $this->applyCartDiscountRules($normalised, $rules, $context, $subtotal);
        }
        if ($coupon) {
            $normalised = $this->applyCouponDiscount($normalised, $coupon);
            $this->coupons->reserve($coupon, $context);
        } elseif ((int) ($context['cart_id'] ?? 0) > 0) {
            $this->coupons->releaseForCart((int) $context['cart_id']);
        }

        $shippingSubtotal = max(0, (int) ($context['shipping_total'] ?? 0));
        $shippingPromotion = $this->shippingDiscount($rules, $normalised, $context, $subtotal, $shippingSubtotal, $coupon, $applyAutomaticDiscounts);
        $shippingDiscount = (int) $shippingPromotion['amount'];
        $shippingNet = max(0, $shippingSubtotal - $shippingDiscount);

        $pricesIncludeTax = (bool) ($context['prices_include_tax'] ?? config('chatbot-ecommerce.pricing.prices_include_tax', false));
        $itemDiscount = array_sum(array_column($normalised, 'discount_total'));
        foreach ($normalised as &$line) {
            $line['net_before_tax'] = max(0, (int) $line['gross_total'] - (int) $line['discount_total']);
        }
        unset($line);

        $taxCalculation = $this->taxes->calculate($normalised, $shippingNet, $pricesIncludeTax, $context);
        $normalised = $taxCalculation['lines'];
        $pricesIncludeTax = (bool) $taxCalculation['prices_include_tax'];
        $shippingTax = (int) $taxCalculation['shipping_tax'];
        $taxTotal = (int) $taxCalculation['tax_total'];
        $shippingTotal = (int) $taxCalculation['shipping_total'];
        $total = array_sum(array_column($normalised, 'line_total')) + $shippingTotal;
        $discountTotal = $itemDiscount + $shippingDiscount;

        $calculation = [
            'calculation_version' => self::CALCULATION_VERSION,
            'currency' => $currency,
            'prices_include_tax' => $pricesIncludeTax,
            'lines' => $normalised,
            'list_subtotal' => $listSubtotal,
            'subtotal' => $subtotal,
            'item_discount' => $itemDiscount,
            'shipping_subtotal' => $shippingSubtotal,
            'shipping_discount' => $shippingDiscount,
            'shipping_discount_source' => $shippingPromotion['source'],
            'shipping' => $shippingNet,
            'shipping_taxable_amount' => (int) $taxCalculation['shipping_taxable_amount'],
            'shipping_tax' => $shippingTax,
            'shipping_total_with_tax' => $shippingTotal,
            'discount' => $discountTotal,
            'tax' => $taxTotal,
            'tax_breakdown' => $taxCalculation['tax_breakdown'],
            'tax_context_hash' => $taxCalculation['tax_context_hash'],
            'tax_zone_id' => $taxCalculation['tax_zone_id'],
            'tax_zone_uuid' => $taxCalculation['tax_zone_uuid'],
            'tax_address' => $taxCalculation['tax_address'],
            'tax_exempt' => $taxCalculation['tax_exempt'],
            'total' => max(0, $total),
            'coupon' => $coupon?->code,
            'coupon_id' => $coupon?->id,
            'applied_rule_ids' => $this->appliedRuleIds($normalised, $shippingPromotion['rule_id']),
        ];
        $snapshotHash = hash('sha256', json_encode($this->canonicalise($calculation), JSON_THROW_ON_ERROR));

        return $calculation + [
            'snapshot_uuid' => (string) Str::uuid(),
            'snapshot_hash' => $snapshotHash,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    public function releaseCouponReservation(int $cartId): int
    {
        return $this->coupons->releaseForCart($cartId);
    }

    public function redeemCouponReservation(int $cartId): int
    {
        return $this->coupons->redeemForCart($cartId);
    }

    /** @param list<array<string, mixed>> $lines */
    private function normaliseLines(array $lines, array $context): array
    {
        $variantIds = collect($lines)
            ->pluck('variant_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $chatbotId = (int) ($context['chatbot_id'] ?? 0);
        $variants = ProductVariant::query()
            ->with(['product.category'])
            ->whereIn('id', $variantIds)
            ->when($chatbotId > 0, fn (Builder $query) => $query->whereHas('product', fn (Builder $product) => $product->where('chatbot_id', $chatbotId)))
            ->get()
            ->keyBy('id');
        $normalised = [];
        $currency = null;

        foreach ($lines as $input) {
            $quantity = CartLineQuantity::normalise((int) ($input['quantity'] ?? 1));
            if ($quantity === 0) {
                continue;
            }

            $variant = $variants->get((int) ($input['variant_id'] ?? 0));
            if (! $variant || ! $variant->active || ! $variant->product || ! $variant->product->active) {
                throw ValidationException::withMessages(['variant_id' => 'The selected product variant is unavailable.']);
            }

            $lineCurrency = strtoupper((string) $variant->product->currency);
            $currency ??= $lineCurrency;
            if ($currency !== $lineCurrency) {
                throw ValidationException::withMessages(['currency' => 'A cart cannot contain multiple currencies.']);
            }
            if (isset($context['currency']) && strtoupper((string) $context['currency']) !== $lineCurrency) {
                throw ValidationException::withMessages(['currency' => 'The requested pricing currency does not match the product currency.']);
            }

            $listPrice = (int) ($variant->price ?? $variant->product->price);
            $lineKey = (string) ($input['line_key'] ?? CartLineKey::make((int) $variant->id, $input['customisation'] ?? []));
            $normalised[] = [
                'line_key' => $lineKey,
                'product_id' => (int) $variant->product_id,
                'category_id' => (int) ($variant->product->category_id ?? 0),
                'variant_id' => (int) $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->product->name,
                'variant_name' => $variant->name,
                'quantity' => $quantity,
                'list_unit_price' => $listPrice,
                'unit_price' => $listPrice,
                'list_total' => $listPrice * $quantity,
                'gross_total' => $listPrice * $quantity,
                'discounts' => [],
                'discount_total' => 0,
                'tax_class' => $variant->product->tax_class ?: 'default',
                'tax_rate_bps' => 0,
                'tax_total' => 0,
                'net_before_tax' => $listPrice * $quantity,
                'line_total' => $listPrice * $quantity,
                'currency' => $lineCurrency,
                'customisation' => $input['customisation'] ?? [],
                'metadata' => $input['metadata'] ?? [],
            ];
        }

        return $normalised;
    }

    /** @param list<array<string, mixed>> $lines */
    private function applyPriceOverrides(array $lines, Collection $rules, array $context, int $subtotal): array
    {
        foreach ($lines as &$line) {
            $rule = $rules->first(fn (PricingRule $candidate): bool =>
                $candidate->scope === 'line'
                && $candidate->rule_type === 'price_override'
                && $this->ruleMatchesLine($candidate, $line, $context, $subtotal)
            );
            if (! $rule) {
                continue;
            }

            $line['unit_price'] = (int) $rule->value;
            $line['gross_total'] = (int) $rule->value * (int) $line['quantity'];
            $line['price_override'] = [
                'rule_id' => (int) $rule->id,
                'rule_uuid' => $rule->uuid,
                'name' => $rule->name,
                'from' => (int) $line['list_unit_price'],
                'to' => (int) $rule->value,
            ];
        }
        unset($line);

        return $lines;
    }

    /** @param list<array<string, mixed>> $lines */
    private function applyLineDiscountRules(array $lines, Collection $rules, array $context, int $subtotal): array
    {
        foreach ($lines as &$line) {
            foreach ($rules as $rule) {
                if ($rule->scope !== 'line' || ! in_array($rule->rule_type, ['percentage_discount', 'fixed_discount'], true)) {
                    continue;
                }
                if (! $this->ruleMatchesLine($rule, $line, $context, $subtotal)) {
                    continue;
                }

                $remaining = max(0, (int) $line['gross_total'] - (int) $line['discount_total']);
                $discount = $rule->rule_type === 'percentage_discount'
                    ? MoneyMath::percentage($remaining, (int) $rule->value)
                    : MoneyMath::clampDiscount(
                        (bool) (($rule->metadata ?? [])['per_unit'] ?? false)
                            ? (int) $rule->value * (int) $line['quantity']
                            : (int) $rule->value,
                        $remaining,
                    );
                $this->appendDiscount($line, $discount, 'pricing_rule', $rule);

                if (! $rule->stackable) {
                    break;
                }
            }
        }
        unset($line);

        return $lines;
    }

    /** @param list<array<string, mixed>> $lines */
    private function applyCartDiscountRules(array $lines, Collection $rules, array $context, int $subtotal): array
    {
        foreach ($rules as $rule) {
            if ($rule->scope !== 'cart' || ! in_array($rule->rule_type, ['percentage_discount', 'fixed_discount'], true)) {
                continue;
            }
            if (! $this->ruleMatchesContext($rule, $context, $subtotal)) {
                continue;
            }

            $indices = $this->eligibleLineIndices($rule, $lines);
            if ($indices === []) {
                continue;
            }
            $eligible = [];
            foreach ($indices as $index) {
                $eligible[] = [
                    'key' => (string) $lines[$index]['line_key'],
                    'amount' => max(0, (int) $lines[$index]['gross_total'] - (int) $lines[$index]['discount_total']),
                ];
            }
            $eligibleTotal = array_sum(array_column($eligible, 'amount'));
            $discount = $rule->rule_type === 'percentage_discount'
                ? MoneyMath::percentage($eligibleTotal, (int) $rule->value)
                : MoneyMath::clampDiscount((int) $rule->value, $eligibleTotal);
            $allocation = DiscountAllocator::allocate($discount, $eligible);

            foreach ($indices as $index) {
                $this->appendDiscount($lines[$index], (int) ($allocation[$lines[$index]['line_key']] ?? 0), 'pricing_rule', $rule);
            }

            if (! $rule->stackable) {
                break;
            }
        }

        return $lines;
    }

    /** @param list<array<string, mixed>> $lines */
    private function applyCouponDiscount(array $lines, Coupon $coupon): array
    {
        if ($coupon->type === 'free_shipping') {
            return $lines;
        }

        $keys = $this->coupons->eligibleLineKeys($coupon, $lines);
        $eligible = [];
        foreach ($lines as $line) {
            if (in_array((string) $line['line_key'], $keys, true)) {
                $eligible[] = [
                    'key' => (string) $line['line_key'],
                    'amount' => max(0, (int) $line['gross_total'] - (int) $line['discount_total']),
                ];
            }
        }
        $eligibleTotal = array_sum(array_column($eligible, 'amount'));
        $discount = $coupon->type === 'percentage'
            ? MoneyMath::percentage($eligibleTotal, min((int) $coupon->value, MoneyMath::BASIS_POINTS))
            : MoneyMath::clampDiscount((int) $coupon->value, $eligibleTotal);
        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (int) $coupon->max_discount_amount);
        }
        $allocation = DiscountAllocator::allocate($discount, $eligible);

        foreach ($lines as &$line) {
            $amount = (int) ($allocation[$line['line_key']] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $line['discounts'][] = [
                'source' => 'coupon',
                'coupon_id' => (int) $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'amount' => $amount,
            ];
            $line['discount_total'] += $amount;
        }
        unset($line);

        return $lines;
    }

    /** @param list<array<string, mixed>> $lines */
    private function shippingDiscount(
        Collection $rules,
        array $lines,
        array $context,
        int $subtotal,
        int $shippingSubtotal,
        ?Coupon $coupon,
        bool $applyAutomaticDiscounts,
    ): array {
        if ($shippingSubtotal <= 0) {
            return ['amount' => 0, 'source' => null, 'rule_id' => null];
        }
        if ($coupon?->type === 'free_shipping') {
            return ['amount' => $shippingSubtotal, 'source' => ['type' => 'coupon', 'coupon_id' => (int) $coupon->id, 'code' => $coupon->code], 'rule_id' => null];
        }
        if (! $applyAutomaticDiscounts) {
            return ['amount' => 0, 'source' => null, 'rule_id' => null];
        }

        foreach ($rules as $rule) {
            if ($rule->scope !== 'shipping' || $rule->rule_type !== 'free_shipping') {
                continue;
            }
            if ($this->ruleMatchesContext($rule, $context, $subtotal) && $this->eligibleLineIndices($rule, $lines) !== []) {
                return ['amount' => $shippingSubtotal, 'source' => ['type' => 'pricing_rule', 'rule_id' => (int) $rule->id, 'name' => $rule->name], 'rule_id' => (int) $rule->id];
            }
        }

        return ['amount' => 0, 'source' => null, 'rule_id' => null];
    }

    private function appendDiscount(array &$line, int $discount, string $source, PricingRule $rule): void
    {
        $remaining = max(0, (int) $line['gross_total'] - (int) $line['discount_total']);
        $discount = MoneyMath::clampDiscount($discount, $remaining);
        if ($discount <= 0) {
            return;
        }

        $line['discounts'][] = [
            'source' => $source,
            'rule_id' => (int) $rule->id,
            'rule_uuid' => $rule->uuid,
            'name' => $rule->name,
            'type' => $rule->rule_type,
            'amount' => $discount,
        ];
        $line['discount_total'] += $discount;
    }

    private function activeRules(array $context): Collection
    {
        if (! Schema::hasTable('ext_chatbot_pricing_rules')) {
            return collect();
        }

        return PricingRule::query()
            ->where('active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when((int) ($context['chatbot_id'] ?? 0) > 0, fn (Builder $query) => $query->where('chatbot_id', (int) $context['chatbot_id']))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    private function ruleMatchesLine(PricingRule $rule, array $line, array $context, int $subtotal): bool
    {
        if (! $this->ruleMatchesContext($rule, $context, $subtotal)) {
            return false;
        }
        if ((int) $line['quantity'] < (int) $rule->min_quantity) {
            return false;
        }

        return match ((string) $rule->target_type) {
            'product' => (int) $rule->target_id === (int) $line['product_id'],
            'variant' => (int) $rule->target_id === (int) $line['variant_id'],
            'category' => (int) $rule->target_id === (int) $line['category_id'],
            default => true,
        };
    }

    private function ruleMatchesContext(PricingRule $rule, array $context, int $subtotal): bool
    {
        if ((int) $rule->min_subtotal > $subtotal) {
            return false;
        }
        if ($rule->currency && strtoupper((string) $rule->currency) !== strtoupper((string) ($context['currency'] ?? ''))) {
            return false;
        }
        if ($rule->channel && (string) $rule->channel !== (string) ($context['channel'] ?? '')) {
            return false;
        }
        if ($rule->customer_identity_id && (int) $rule->customer_identity_id !== (int) ($context['customer_identity_id'] ?? 0)) {
            return false;
        }

        return true;
    }

    /** @param list<array<string, mixed>> $lines @return list<int> */
    private function eligibleLineIndices(PricingRule $rule, array $lines): array
    {
        $indices = [];
        foreach ($lines as $index => $line) {
            $matches = match ((string) $rule->target_type) {
                'product' => (int) $rule->target_id === (int) $line['product_id'],
                'variant' => (int) $rule->target_id === (int) $line['variant_id'],
                'category' => (int) $rule->target_id === (int) $line['category_id'],
                default => true,
            };
            if ($matches && (int) $line['quantity'] >= (int) $rule->min_quantity) {
                $indices[] = $index;
            }
        }

        return $indices;
    }

    /** @param list<array<string, mixed>> $lines @return list<int> */
    private function appliedRuleIds(array $lines, ?int $shippingRuleId = null): array
    {
        $ids = [];
        foreach ($lines as $line) {
            if (isset($line['price_override']['rule_id'])) {
                $ids[] = (int) $line['price_override']['rule_id'];
            }
            foreach ($line['discounts'] ?? [] as $discount) {
                if (isset($discount['rule_id'])) {
                    $ids[] = (int) $discount['rule_id'];
                }
            }
        }

        if ($shippingRuleId !== null) {
            $ids[] = $shippingRuleId;
        }

        return array_values(array_unique($ids));
    }

    private function canonicalise(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalise($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
