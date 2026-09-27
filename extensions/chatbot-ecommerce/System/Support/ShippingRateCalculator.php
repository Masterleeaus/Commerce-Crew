<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;

final class ShippingRateCalculator
{
    /** @param array<string, mixed> $rate */
    public static function calculate(array $rate, int $subtotal, int $weightGrams): int
    {
        $subtotal = max(0, $subtotal);
        $weightGrams = max(0, $weightGrams);
        $freeAbove = isset($rate['free_above']) ? max(0, (int) $rate['free_above']) : null;
        if ($freeAbove !== null && $subtotal >= $freeAbove) {
            return 0;
        }

        return match (strtolower((string) ($rate['rate_type'] ?? 'flat'))) {
            'flat' => max(0, (int) ($rate['amount'] ?? 0)),
            'weight' => self::weightAmount($rate, $weightGrams),
            'subtotal' => self::subtotalAmount($rate, $subtotal),
            'free' => 0,
            default => throw new InvalidArgumentException('Unsupported shipping rate type.'),
        };
    }

    /** @param array<string, mixed> $rate */
    private static function weightAmount(array $rate, int $weightGrams): int
    {
        $base = max(0, (int) ($rate['base_amount'] ?? $rate['amount'] ?? 0));
        $perKilogram = max(0, (int) ($rate['per_kg_amount'] ?? 0));
        $kilograms = $weightGrams > 0 ? (int) ceil($weightGrams / 1000) : 0;

        return $base + ($kilograms * $perKilogram);
    }

    /** @param array<string, mixed> $rate */
    private static function subtotalAmount(array $rate, int $subtotal): int
    {
        foreach ((array) ($rate['tiers'] ?? []) as $tier) {
            if (! is_array($tier)) {
                continue;
            }
            $minimum = max(0, (int) ($tier['min'] ?? 0));
            $maximum = array_key_exists('max', $tier) ? max(0, (int) $tier['max']) : null;
            if ($subtotal >= $minimum && ($maximum === null || $subtotal <= $maximum)) {
                return max(0, (int) ($tier['amount'] ?? 0));
            }
        }

        throw new InvalidArgumentException('No subtotal shipping tier matched.');
    }
}
