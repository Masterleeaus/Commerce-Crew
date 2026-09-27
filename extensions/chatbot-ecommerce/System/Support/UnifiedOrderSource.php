<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class UnifiedOrderSource
{
    private const INTERNAL = ['native', 'internal'];

    public static function normalize(string $source): string
    {
        $source = strtolower(trim($source));
        return $source === 'internal' ? 'native' : ($source !== '' ? $source : 'unknown');
    }

    public static function isExternal(string $source): bool
    {
        return ! in_array(self::normalize($source), self::INTERNAL, true);
    }

    public static function externalAuthoritative(string $source): bool
    {
        return self::isExternal($source);
    }
}
