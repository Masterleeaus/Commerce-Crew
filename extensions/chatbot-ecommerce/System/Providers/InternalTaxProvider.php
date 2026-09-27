<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Providers;

use App\Extensions\ChatbotEcommerce\System\Contracts\TaxProvider;
use App\Extensions\ChatbotEcommerce\System\Models\TaxRate;
use App\Extensions\ChatbotEcommerce\System\Models\TaxZone;
use App\Extensions\ChatbotEcommerce\System\Support\TaxCalculator;
use App\Extensions\ChatbotEcommerce\System\Support\TaxZoneMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class InternalTaxProvider implements TaxProvider
{
    public function resolveZone(array $address, array $context = []): ?array
    {
        if (! Schema::hasTable('ext_chatbot_tax_zones')) {
            return null;
        }

        $chatbotId = (int) ($context['chatbot_id'] ?? 0);
        $zones = TaxZone::query()
            ->where('active', true)
            ->when($chatbotId > 0, fn (Builder $query) => $query->where('chatbot_id', $chatbotId))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        foreach ($zones as $zone) {
            if (! TaxZoneMatcher::matches($address, [
                'countries' => $zone->countries ?? [],
                'regions' => $zone->regions ?? [],
                'postcode_patterns' => $zone->postcode_patterns ?? [],
            ])) {
                continue;
            }

            return [
                'id' => (int) $zone->id,
                'uuid' => $zone->uuid,
                'name' => $zone->name,
                'priority' => (int) $zone->priority,
                'prices_include_tax' => $zone->prices_include_tax,
                'metadata' => $zone->metadata ?? [],
            ];
        }

        return null;
    }

    public function ratesForLine(array $line, ?array $zone, array $context = []): array
    {
        $taxClass = trim((string) ($line['tax_class'] ?? 'default')) ?: 'default';
        $rates = $this->databaseRates($zone, $context, $taxClass, false);
        if ($rates !== []) {
            return $rates;
        }

        return $this->configuredRates($taxClass, $context, false);
    }

    public function ratesForShipping(?array $zone, array $context = []): array
    {
        $rates = $this->databaseRates($zone, $context, 'shipping', true);
        if ($rates !== []) {
            return $rates;
        }

        return $this->configuredRates('shipping', $context, true);
    }

    /** @return list<array<string,mixed>> */
    private function databaseRates(?array $zone, array $context, string $taxClass, bool $shipping): array
    {
        if (! Schema::hasTable('ext_chatbot_tax_rates')) {
            return [];
        }

        $chatbotId = (int) ($context['chatbot_id'] ?? 0);
        $zoneId = isset($zone['id']) ? (int) $zone['id'] : null;
        $base = TaxRate::query()
            ->where('active', true)
            ->where($shipping ? 'applies_to_shipping' : 'applies_to_products', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when($chatbotId > 0, fn (Builder $query) => $query->where('chatbot_id', $chatbotId))
            ->when($zoneId !== null,
                fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                    ->whereNull('tax_zone_id')
                    ->orWhere('tax_zone_id', $zoneId)),
                fn (Builder $query) => $query->whereNull('tax_zone_id'));

        $classes = $shipping ? ['shipping'] : [$taxClass];
        $rates = (clone $base)
            ->whereIn('tax_class', $classes)
            ->orderByRaw('tax_zone_id IS NULL')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($rates->isEmpty() && $taxClass !== 'default') {
            $rates = (clone $base)
                ->where('tax_class', 'default')
                ->orderByRaw('tax_zone_id IS NULL')
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();
        }

        return $this->ratePayloads($rates);
    }

    /** @return list<array<string,mixed>> */
    private function configuredRates(string $taxClass, array $context, bool $shipping): array
    {
        $configured = (array) ($context['tax_rates'] ?? config('chatbot-ecommerce.pricing.tax_rates', []));
        $value = $shipping
            ? ($configured['shipping'] ?? $context['shipping_tax_rate_bps'] ?? config('chatbot-ecommerce.pricing.shipping_tax_rate_bps', 0))
            : ($configured[$taxClass] ?? $configured['default'] ?? $context['default_tax_rate_bps'] ?? config('chatbot-ecommerce.pricing.default_tax_rate_bps', 0));

        if (is_array($value)) {
            $components = array_is_list($value) ? $value : [$value];
            $payloads = [];
            foreach ($components as $index => $component) {
                if (! is_array($component)) {
                    continue;
                }
                $rate = TaxCalculator::normaliseRateBps((int) ($component['rate_bps'] ?? $component['rate'] ?? 0));
                if ($rate <= 0) {
                    continue;
                }
                $payloads[] = [
                    'rate_id' => null,
                    'rate_uuid' => null,
                    'code' => (string) ($component['code'] ?? ($shipping ? 'shipping_tax' : $taxClass . '_tax_' . ($index + 1))),
                    'name' => (string) ($component['name'] ?? 'Tax'),
                    'rate_bps' => $rate,
                    'compound' => (bool) ($component['compound'] ?? false),
                    'metadata' => (array) ($component['metadata'] ?? []),
                ];
            }
            return $payloads;
        }

        $rate = TaxCalculator::normaliseRateBps((int) $value);
        if ($rate <= 0) {
            return [];
        }

        return [[
            'rate_id' => null,
            'rate_uuid' => null,
            'code' => $shipping ? 'shipping_tax' : $taxClass . '_tax',
            'name' => $shipping ? 'Shipping tax' : 'Tax',
            'rate_bps' => $rate,
            'compound' => false,
            'metadata' => ['source' => 'configuration'],
        ]];
    }

    /** @return list<array<string,mixed>> */
    private function ratePayloads(Collection $rates): array
    {
        return $rates->map(static fn (TaxRate $rate): array => [
            'rate_id' => (int) $rate->id,
            'rate_uuid' => $rate->uuid,
            'code' => $rate->code,
            'name' => $rate->name,
            'rate_bps' => (int) $rate->rate_bps,
            'compound' => (bool) $rate->compound,
            'metadata' => $rate->metadata ?? [],
        ])->values()->all();
    }
}
