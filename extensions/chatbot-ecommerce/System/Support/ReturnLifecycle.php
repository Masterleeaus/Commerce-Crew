<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class ReturnLifecycle
{
    /** @var array<string,array<int,string>> */
    private const TRANSITIONS = [
        'requested' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['in_transit', 'received', 'cancelled'],
        'in_transit' => ['received', 'cancelled'],
        'received' => ['inspected', 'refunded', 'exchanged'],
        'inspected' => ['refunded', 'exchanged', 'rejected'],
        'rejected' => [],
        'cancelled' => [],
        'refunded' => [],
        'exchanged' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
