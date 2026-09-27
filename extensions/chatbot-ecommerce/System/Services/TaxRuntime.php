<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Contracts\TaxProvider;
use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\TaxExemption;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutAddress;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use App\Extensions\ChatbotEcommerce\System\Support\TaxCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class TaxRuntime
{
    public function __construct(private readonly TaxProvider $provider) {}

    /**
     * @param list<array<string,mixed>> $lines
     * @return array<string,mixed>
     */
    public function calculate(array $lines, int $shippingAmount, bool $pricesIncludeTax, array $context = []): array
    {
        $resolved = $this->resolveContext($context);
        $zone = $this->provider->resolveZone($resolved['tax_address'], $resolved);
        if ($zone !== null && array_key_exists('prices_include_tax', $zone) && $zone['prices_include_tax'] !== null) {
            $pricesIncludeTax = (bool) $zone['prices_include_tax'];
        }

        $taxTotal = 0;
        $aggregate = [];
        $lineBreakdown = [];
        $globallyExempt = $this->isExempt($resolved, null);

        foreach ($lines as &$line) {
            $taxClass = trim((string) ($line['tax_class'] ?? 'default')) ?: 'default';
            $lineExempt = $globallyExempt || $this->isExempt($resolved, $taxClass);
            $components = $lineExempt ? [] : $this->provider->ratesForLine($line, $zone, $resolved);
            $calculation = TaxCalculator::calculate(max(0, (int) ($line['net_before_tax'] ?? 0)), $components, $pricesIncludeTax);

            $line['net_before_tax'] = (int) $calculation['base_amount'];
            $line['tax_rate_bps'] = array_sum(array_map(static fn (array $component): int => (int) $component['rate_bps'], $calculation['components']));
            $line['tax_total'] = (int) $calculation['tax_total'];
            $line['tax_components'] = $calculation['components'];
            $line['tax_exempt'] = $lineExempt;
            $line['line_total'] = (int) $calculation['total'];
            $taxTotal += (int) $calculation['tax_total'];
            $lineBreakdown[(string) ($line['line_key'] ?? count($lineBreakdown))] = [
                'tax_class' => $taxClass,
                'exempt' => $lineExempt,
                'taxable_amount' => (int) $calculation['base_amount'],
                'tax_total' => (int) $calculation['tax_total'],
                'components' => $calculation['components'],
            ];
            $this->aggregate($aggregate, $calculation['components']);
        }
        unset($line);

        $shippingExempt = $globallyExempt || $this->isExempt($resolved, 'shipping');
        $shippingComponents = $shippingExempt ? [] : $this->provider->ratesForShipping($zone, $resolved);
        $shipping = TaxCalculator::calculate(max(0, $shippingAmount), $shippingComponents, $pricesIncludeTax);
        $taxTotal += (int) $shipping['tax_total'];
        $this->aggregate($aggregate, $shipping['components']);
        $contextHash = $this->contextHash($resolved, $zone, $pricesIncludeTax);

        $breakdown = [
            'zone' => $zone,
            'prices_include_tax' => $pricesIncludeTax,
            'globally_exempt' => $globallyExempt,
            'lines' => $lineBreakdown,
            'shipping' => [
                'exempt' => $shippingExempt,
                'taxable_amount' => (int) $shipping['base_amount'],
                'tax_total' => (int) $shipping['tax_total'],
                'components' => $shipping['components'],
            ],
            'components' => array_values($aggregate),
        ];

        Metrics::increment('tax.calculated', ['zone' => $zone['uuid'] ?? 'none']);
        event(new ExtensionEvent('tax.calculated', [
            'tax_total' => $taxTotal,
            'tax_context_hash' => $contextHash,
            'tax_zone_uuid' => $zone['uuid'] ?? null,
        ]));

        return [
            'lines' => $lines,
            'prices_include_tax' => $pricesIncludeTax,
            'shipping_taxable_amount' => (int) $shipping['base_amount'],
            'shipping_tax' => (int) $shipping['tax_total'],
            'shipping_total' => (int) $shipping['total'],
            'tax_total' => $taxTotal,
            'tax_breakdown' => $breakdown,
            'tax_context_hash' => $contextHash,
            'tax_zone_id' => $zone['id'] ?? null,
            'tax_zone_uuid' => $zone['uuid'] ?? null,
            'tax_address' => $resolved['tax_address'],
            'tax_exempt' => $globallyExempt,
        ];
    }

    /** @return array<string,mixed> */
    public function resolveContext(array $context): array
    {
        $shipping = CheckoutAddress::normalise((array) ($context['shipping_address'] ?? []));
        $billing = CheckoutAddress::normalise((array) ($context['billing_address'] ?? []));
        $explicit = CheckoutAddress::normalise((array) ($context['tax_address'] ?? []));
        $taxAddress = $this->hasAddress($explicit) ? $explicit : ($this->hasAddress($shipping) ? $shipping : $billing);

        return array_replace($context, [
            'tax_address' => $taxAddress,
            'shipping_address' => $shipping,
            'billing_address' => $billing,
            'tax_exemption_key' => isset($context['tax_exemption_key']) ? trim((string) $context['tax_exemption_key']) : null,
            'tax_exempt' => (bool) ($context['tax_exempt'] ?? false),
        ]);
    }

    public function isExempt(array $context, ?string $taxClass = null): bool
    {
        if ((bool) ($context['tax_exempt'] ?? false)) {
            return true;
        }
        if (! Schema::hasTable('ext_chatbot_tax_exemptions')) {
            return false;
        }

        $key = trim((string) ($context['tax_exemption_key'] ?? ''));
        $customerId = (int) ($context['customer_identity_id'] ?? 0);
        if ($key === '' && $customerId <= 0) {
            return false;
        }

        $chatbotId = (int) ($context['chatbot_id'] ?? 0);
        return TaxExemption::query()
            ->where('active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when($chatbotId > 0, fn (Builder $query) => $query->where('chatbot_id', $chatbotId))
            ->where(function (Builder $query) use ($key, $customerId): void {
                if ($key !== '') {
                    $query->where('exemption_key', $key);
                    if ($customerId > 0) {
                        $query->orWhere('customer_identity_id', $customerId);
                    }
                    return;
                }
                $query->where('customer_identity_id', $customerId);
            })
            ->when($taxClass !== null, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->whereNull('tax_class')
                ->orWhere('tax_class', $taxClass)), fn (Builder $query) => $query->whereNull('tax_class'))
            ->exists();
    }

    public function contextHash(array $context, ?array $zone = null, ?bool $pricesIncludeTax = null): string
    {
        $resolved = $this->resolveContext($context);
        $payload = [
            'tax_address' => $resolved['tax_address'],
            'chatbot_id' => (int) ($resolved['chatbot_id'] ?? 0),
            'customer_identity_id' => isset($resolved['customer_identity_id']) ? (int) $resolved['customer_identity_id'] : null,
            'tax_exemption_key' => $resolved['tax_exemption_key'],
            'tax_exempt' => (bool) $resolved['tax_exempt'],
            'zone_uuid' => $zone['uuid'] ?? null,
            'prices_include_tax' => $pricesIncludeTax,
        ];

        return hash('sha256', json_encode($this->canonicalise($payload), JSON_THROW_ON_ERROR));
    }

    /** @param array<string,array<string,mixed>> $aggregate @param list<array<string,mixed>> $components */
    private function aggregate(array &$aggregate, array $components): void
    {
        foreach ($components as $component) {
            $key = (string) ($component['rate_uuid'] ?? $component['code'] ?? 'tax');
            if (! isset($aggregate[$key])) {
                $aggregate[$key] = [
                    'rate_id' => $component['rate_id'] ?? null,
                    'rate_uuid' => $component['rate_uuid'] ?? null,
                    'code' => $component['code'] ?? 'tax',
                    'name' => $component['name'] ?? 'Tax',
                    'rate_bps' => (int) ($component['rate_bps'] ?? 0),
                    'amount' => 0,
                ];
            }
            $aggregate[$key]['amount'] += (int) ($component['amount'] ?? 0);
        }
    }

    private function hasAddress(array $address): bool
    {
        return trim((string) ($address['country'] ?? '')) !== ''
            || trim((string) ($address['region'] ?? '')) !== ''
            || trim((string) ($address['postcode'] ?? '')) !== '';
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
