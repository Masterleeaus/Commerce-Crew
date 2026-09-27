<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MarketplaceSearchState
{
    public const QUEUED = 'queued';
    public const SEARCHING = 'searching';
    public const PARTIAL = 'partial';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, match ($from) {
            self::QUEUED => [self::SEARCHING, self::FAILED, self::EXPIRED],
            self::SEARCHING => [self::PARTIAL, self::COMPLETED, self::FAILED],
            self::PARTIAL => [self::PARTIAL, self::COMPLETED, self::FAILED],
            default => [],
        }, true);
    }
}
