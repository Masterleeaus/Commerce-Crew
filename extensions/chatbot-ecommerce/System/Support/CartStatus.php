<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CartStatus
{
    public const ACTIVE = 'active';
    public const ABANDONED = 'abandoned';
    public const CONVERTED = 'converted';
    public const EXPIRED = 'expired';
    public const MERGED = 'merged';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::ACTIVE, self::ABANDONED, self::CONVERTED, self::EXPIRED, self::MERGED];
    }

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, match ($from) {
            self::ACTIVE => [self::ABANDONED, self::CONVERTED, self::EXPIRED, self::MERGED],
            self::ABANDONED => [self::ACTIVE, self::EXPIRED, self::MERGED],
            default => [],
        }, true);
    }

    public static function isMutable(string $status): bool
    {
        return in_array($status, [self::ACTIVE, self::ABANDONED], true);
    }
}
