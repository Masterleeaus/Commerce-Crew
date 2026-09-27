<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class ShoppingMode
{
    public const NATIVE = 'native';
    public const MARKETPLACE_ASSISTED = 'marketplace_assisted';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::NATIVE, self::MARKETPLACE_ASSISTED];
    }

    public static function normalize(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return in_array($mode, self::all(), true) ? $mode : self::NATIVE;
    }
}
