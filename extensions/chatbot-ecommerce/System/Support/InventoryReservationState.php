<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class InventoryReservationState
{
    public const ACTIVE = 'active';
    public const COMMITTED = 'committed';
    public const RELEASED = 'released';
    public const EXPIRED = 'expired';

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::ACTIVE => [self::COMMITTED, self::RELEASED, self::EXPIRED],
        self::COMMITTED => [],
        self::RELEASED => [],
        self::EXPIRED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::COMMITTED, self::RELEASED, self::EXPIRED], true);
    }
}
