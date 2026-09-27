<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class RetryBackoff
{
    public static function seconds(int $attempt, int $baseSeconds = 5, int $maximumSeconds = 900): int
    {
        $attempt = max(1, $attempt);
        $baseSeconds = max(1, $baseSeconds);
        $maximumSeconds = max($baseSeconds, $maximumSeconds);

        return min($maximumSeconds, $baseSeconds * (2 ** min(20, $attempt - 1)));
    }

    /** @return list<int> */
    public static function schedule(int $attempts, int $baseSeconds = 5, int $maximumSeconds = 900): array
    {
        $result = [];
        for ($attempt = 1; $attempt <= max(1, $attempts); $attempt++) {
            $result[] = self::seconds($attempt, $baseSeconds, $maximumSeconds);
        }

        return $result;
    }
}
