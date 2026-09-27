<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class OrderLifecycle
{
    /** @var array<string,array<int,string>> */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'payment_failed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled', 'partially_refunded', 'refunded'],
        'processing' => ['partially_fulfilled', 'fulfilled', 'cancelled', 'partially_refunded', 'refunded'],
        'partially_fulfilled' => ['fulfilled', 'partially_refunded', 'refunded'],
        'fulfilled' => ['completed', 'partially_refunded', 'refunded', 'returned'],
        'completed' => ['partially_refunded', 'refunded', 'returned'],
        'partially_refunded' => ['refunded', 'returned'],
        'returned' => ['partially_refunded', 'refunded'],
        'payment_failed' => ['pending', 'cancelled'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
