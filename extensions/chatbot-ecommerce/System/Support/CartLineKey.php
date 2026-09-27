<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CartLineKey
{
    public static function make(int $variantId, array $customisation = []): string
    {
        return hash('sha256', $variantId . '|' . json_encode(self::canonicalise($customisation), JSON_THROW_ON_ERROR));
    }

    private static function canonicalise(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonicalise($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        return $value;
    }
}
