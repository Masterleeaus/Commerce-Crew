<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Providers;

use App\Extensions\ChatbotEcommerce\System\Contracts\ShippingProvider;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\ShippingMethod;
use App\Extensions\ChatbotEcommerce\System\Support\ShippingRateCalculator;
use App\Extensions\ChatbotEcommerce\System\Support\ShippingZoneMatcher;
use Illuminate\Support\Facades\Schema;

final class InternalShippingProvider implements ShippingProvider
{
    public function quotes(ChatbotCart $cart, array $address, array $context = []): array
    {
        $cart->loadMissing('cartLines.variant.product');
        $subtotal = (int) $cart->subtotal;
        $weightGrams = $this->weightGrams($cart);
        $requiresDelivery = $this->requiresDelivery($cart);
        $quotes = [];

        if (Schema::hasTable('ext_chatbot_shipping_methods')) {
            $methods = ShippingMethod::query()
                ->with('zone')
                ->where('active', true)
                ->where('chatbot_id', $cart->chatbot_id)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();

            foreach ($methods as $method) {
                $type = strtolower((string) $method->method_type);
                if ($type === 'delivery' && ! $requiresDelivery) {
                    continue;
                }
                if ($type === 'digital' && $requiresDelivery) {
                    continue;
                }
                if ($type === 'delivery' && $method->zone) {
                    if (! $method->zone->active || ! ShippingZoneMatcher::matches($address, [
                        'countries' => $method->zone->countries ?? [],
                        'regions' => $method->zone->regions ?? [],
                        'postcode_patterns' => $method->zone->postcode_patterns ?? [],
                    ])) {
                        continue;
                    }
                }
                if (strtoupper((string) $method->currency) !== strtoupper((string) $cart->currency)) {
                    continue;
                }
                if ($subtotal < (int) $method->minimum_subtotal || ($method->maximum_subtotal !== null && $subtotal > (int) $method->maximum_subtotal)) {
                    continue;
                }
                if ($weightGrams < (int) $method->minimum_weight_grams || ($method->maximum_weight_grams !== null && $weightGrams > (int) $method->maximum_weight_grams)) {
                    continue;
                }

                $configuration = (array) ($method->configuration ?? []);
                $amount = in_array($type, ['pickup', 'digital'], true) ? 0 : ShippingRateCalculator::calculate([
                    'rate_type' => $method->rate_type,
                    'amount' => (int) $method->amount,
                    'base_amount' => (int) $method->base_amount,
                    'per_kg_amount' => (int) $method->per_kg_amount,
                    'free_above' => $method->free_above,
                    'tiers' => $configuration['tiers'] ?? [],
                ], $subtotal, $weightGrams);

                $quotes[] = [
                    'shipping_method_id' => (int) $method->id,
                    'code' => (string) $method->code,
                    'method_type' => $type,
                    'name' => (string) $method->name,
                    'currency' => strtoupper((string) $method->currency),
                    'amount' => $amount,
                    'subtotal' => $subtotal,
                    'weight_grams' => $weightGrams,
                    'estimated_days_min' => $method->estimated_days_min !== null ? (int) $method->estimated_days_min : null,
                    'estimated_days_max' => $method->estimated_days_max !== null ? (int) $method->estimated_days_max : null,
                    'source' => 'internal',
                    'metadata' => (array) ($method->metadata ?? []),
                ];
            }
        }

        foreach ($this->configuredQuotes($cart, $requiresDelivery, $subtotal, $weightGrams) as $configured) {
            if (! collect($quotes)->contains(fn (array $quote): bool => $quote['code'] === $configured['code'])) {
                $quotes[] = $configured;
            }
        }

        if (! $requiresDelivery && ! collect($quotes)->contains(fn (array $quote): bool => $quote['method_type'] === 'digital')) {
            $quotes[] = [
                'shipping_method_id' => null,
                'code' => 'digital',
                'method_type' => 'digital',
                'name' => 'Digital delivery',
                'currency' => strtoupper((string) $cart->currency),
                'amount' => 0,
                'subtotal' => $subtotal,
                'weight_grams' => 0,
                'estimated_days_min' => 0,
                'estimated_days_max' => 0,
                'source' => 'built_in',
                'metadata' => [],
            ];
        }

        return array_values($quotes);
    }

    /** @return list<array<string, mixed>> */
    private function configuredQuotes(ChatbotCart $cart, bool $requiresDelivery, int $subtotal, int $weightGrams): array
    {
        $quotes = [];
        foreach ((array) config('chatbot-ecommerce.checkout.delivery_options', []) as $code => $option) {
            if (! is_array($option)) {
                continue;
            }
            $type = strtolower((string) ($option['method'] ?? $code));
            if ($type === 'delivery' && ! $requiresDelivery) {
                continue;
            }
            if ($type === 'digital' && $requiresDelivery) {
                continue;
            }
            $quotes[] = [
                'shipping_method_id' => null,
                'code' => (string) $code,
                'method_type' => $type,
                'name' => (string) ($option['name'] ?? ucfirst($type)),
                'currency' => strtoupper((string) $cart->currency),
                'amount' => in_array($type, ['pickup', 'digital'], true) ? 0 : max(0, (int) ($option['amount'] ?? 0)),
                'subtotal' => $subtotal,
                'weight_grams' => $weightGrams,
                'estimated_days_min' => isset($option['estimated_days_min']) ? (int) $option['estimated_days_min'] : null,
                'estimated_days_max' => isset($option['estimated_days_max']) ? (int) $option['estimated_days_max'] : null,
                'source' => 'configuration',
                'metadata' => (array) ($option['metadata'] ?? []),
            ];
        }

        return $quotes;
    }

    private function weightGrams(ChatbotCart $cart): int
    {
        $weight = 0;
        foreach ($cart->cartLines as $line) {
            $productType = strtolower((string) ($line->variant?->product?->product_type ?? 'physical'));
            if (in_array($productType, ['digital', 'service'], true)) {
                continue;
            }
            $kilograms = max(0.0, (float) ($line->variant?->weight ?? 0));
            $weight += (int) round($kilograms * 1000) * (int) $line->quantity;
        }

        return $weight;
    }

    public function requiresDelivery(ChatbotCart $cart): bool
    {
        foreach ($cart->cartLines as $line) {
            $productType = strtolower((string) ($line->variant?->product?->product_type ?? 'physical'));
            if (! in_array($productType, ['digital', 'service'], true)) {
                return true;
            }
        }

        return false;
    }
}
