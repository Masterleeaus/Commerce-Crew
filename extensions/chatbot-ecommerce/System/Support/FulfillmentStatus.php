<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class FulfillmentStatus
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::PENDING => [self::PROCESSING, self::SHIPPED, self::CANCELLED],
        self::PROCESSING => [self::SHIPPED, self::CANCELLED],
        self::SHIPPED => [self::DELIVERED],
        self::DELIVERED => [],
        self::CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::DELIVERED, self::CANCELLED], true);
    }

    public static function aggregate(int $totalQuantity, int $fulfilledQuantity, int $deliveredQuantity): string
    {
        if ($totalQuantity < 1 || $fulfilledQuantity < 1) {
            return 'unfulfilled';
        }
        if ($deliveredQuantity >= $totalQuantity) {
            return 'delivered';
        }
        if ($fulfilledQuantity >= $totalQuantity) {
            return 'fulfilled';
        }

        return 'partially_fulfilled';
    }
}
