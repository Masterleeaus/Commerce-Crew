<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MoneyMath
{
    public const BASIS_POINTS = 10_000;

    public static function percentage(int $amount, int $basisPoints): int
    {
        if ($amount <= 0 || $basisPoints <= 0) {
            return 0;
        }

        $basisPoints = min($basisPoints, self::BASIS_POINTS);

        return intdiv(($amount * $basisPoints) + intdiv(self::BASIS_POINTS, 2), self::BASIS_POINTS);
    }

    public static function clampDiscount(int $discount, int $amount): int
    {
        return min(max($discount, 0), max($amount, 0));
    }

    public static function exclusiveTax(int $netAmount, int $basisPoints): int
    {
        return self::percentage($netAmount, $basisPoints);
    }

    public static function inclusiveTax(int $grossAmount, int $basisPoints): int
    {
        if ($grossAmount <= 0 || $basisPoints <= 0) {
            return 0;
        }

        $denominator = self::BASIS_POINTS + $basisPoints;

        return intdiv(($grossAmount * $basisPoints) + intdiv($denominator, 2), $denominator);
    }
}
