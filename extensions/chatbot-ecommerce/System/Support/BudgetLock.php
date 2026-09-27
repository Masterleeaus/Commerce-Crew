<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class BudgetLock
{
    /** @param array<string,mixed> $limits @return array{allowed:bool,reason:string|null,remaining_daily:int|null} */
    public static function evaluate(int $amount, string $currency, array $limits): array
    {
        $amount = max(0, $amount);
        $configuredCurrency = strtoupper((string) ($limits['currency'] ?? $currency));
        $currency = strtoupper($currency);

        if ($configuredCurrency !== '' && $configuredCurrency !== $currency) {
            return ['allowed' => false, 'reason' => 'currency_mismatch', 'remaining_daily' => null];
        }

        $maxOrder = isset($limits['max_order_value']) ? (int) $limits['max_order_value'] : null;
        if ($maxOrder !== null && $maxOrder > 0 && $amount > $maxOrder) {
            return ['allowed' => false, 'reason' => 'max_order_value', 'remaining_daily' => self::remaining($limits)];
        }

        $daily = isset($limits['daily_spend_limit']) ? (int) $limits['daily_spend_limit'] : null;
        $spent = max(0, (int) ($limits['spent_today'] ?? 0));
        if ($daily !== null && $daily > 0 && ($spent + $amount) > $daily) {
            return ['allowed' => false, 'reason' => 'daily_spend_limit', 'remaining_daily' => max(0, $daily - $spent)];
        }

        return ['allowed' => true, 'reason' => null, 'remaining_daily' => $daily !== null && $daily > 0 ? max(0, $daily - $spent - $amount) : null];
    }

    /** @param array<string,mixed> $limits */
    private static function remaining(array $limits): ?int
    {
        $daily = isset($limits['daily_spend_limit']) ? (int) $limits['daily_spend_limit'] : null;
        if ($daily === null || $daily <= 0) {
            return null;
        }

        return max(0, $daily - max(0, (int) ($limits['spent_today'] ?? 0)));
    }
}
