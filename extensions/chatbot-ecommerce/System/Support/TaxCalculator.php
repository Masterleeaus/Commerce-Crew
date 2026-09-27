<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class TaxCalculator
{
    public const BASIS_POINTS = 10000;
    public const MAX_RATE_BPS = 100000;

    /**
     * @param list<array<string, mixed>> $components
     * @return array{base_amount:int,tax_total:int,total:int,components:list<array<string,mixed>>}
     */
    public static function calculate(int $amount, array $components, bool $pricesIncludeTax): array
    {
        $amount = max(0, $amount);
        $normalised = self::normaliseComponents($components);
        if ($amount === 0 || $normalised === []) {
            return [
                'base_amount' => $amount,
                'tax_total' => 0,
                'total' => $amount,
                'components' => [],
            ];
        }

        $base = $pricesIncludeTax
            ? self::inclusiveBase($amount, $normalised)
            : $amount;
        $taxComponents = self::exclusiveComponents($base, $normalised);
        $taxTotal = array_sum(array_column($taxComponents, 'amount'));

        if ($pricesIncludeTax) {
            $expectedTax = max(0, $amount - $base);
            $delta = $expectedTax - $taxTotal;
            if ($delta !== 0 && $taxComponents !== []) {
                $last = array_key_last($taxComponents);
                $taxComponents[$last]['amount'] = max(0, (int) $taxComponents[$last]['amount'] + $delta);
                $taxTotal = array_sum(array_column($taxComponents, 'amount'));
            }
        }

        return [
            'base_amount' => $base,
            'tax_total' => $taxTotal,
            'total' => $pricesIncludeTax ? $amount : $base + $taxTotal,
            'components' => $taxComponents,
        ];
    }

    public static function normaliseRateBps(int $rateBps): int
    {
        return min(max($rateBps, 0), self::MAX_RATE_BPS);
    }

    /** @param list<array<string,mixed>> $components @return list<array<string,mixed>> */
    private static function normaliseComponents(array $components): array
    {
        $normalised = [];
        foreach ($components as $index => $component) {
            $rate = self::normaliseRateBps((int) ($component['rate_bps'] ?? 0));
            if ($rate <= 0) {
                continue;
            }
            $normalised[] = [
                'rate_id' => isset($component['rate_id']) ? (int) $component['rate_id'] : null,
                'rate_uuid' => $component['rate_uuid'] ?? null,
                'code' => (string) ($component['code'] ?? 'tax_' . ($index + 1)),
                'name' => (string) ($component['name'] ?? 'Tax'),
                'rate_bps' => $rate,
                'compound' => (bool) ($component['compound'] ?? false),
                'metadata' => (array) ($component['metadata'] ?? []),
            ];
        }

        return $normalised;
    }

    /** @param list<array<string,mixed>> $components */
    private static function inclusiveBase(int $inclusiveAmount, array $components): int
    {
        $factorScaled = self::BASIS_POINTS;
        foreach ($components as $component) {
            $rate = (int) $component['rate_bps'];
            $taxFactor = (bool) $component['compound']
                ? self::roundedDivide($factorScaled * $rate, self::BASIS_POINTS)
                : $rate;
            $factorScaled += $taxFactor;
        }

        return self::roundedDivide($inclusiveAmount * self::BASIS_POINTS, max($factorScaled, 1));
    }

    /** @param list<array<string,mixed>> $components @return list<array<string,mixed>> */
    private static function exclusiveComponents(int $base, array $components): array
    {
        $result = [];
        $taxSoFar = 0;
        foreach ($components as $component) {
            $taxBase = (bool) $component['compound'] ? $base + $taxSoFar : $base;
            $amount = self::roundedDivide($taxBase * (int) $component['rate_bps'], self::BASIS_POINTS);
            $result[] = $component + [
                'taxable_amount' => $taxBase,
                'amount' => $amount,
            ];
            $taxSoFar += $amount;
        }

        return $result;
    }

    private static function roundedDivide(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            return 0;
        }

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
