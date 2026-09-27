<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class PaymentStatus
{
    public const PENDING = 'pending';
    public const REQUIRES_ACTION = 'requires_action';
    public const AUTHORIZED = 'authorized';
    public const PARTIALLY_CAPTURED = 'partially_captured';
    public const CAPTURED = 'captured';
    public const PARTIALLY_REFUNDED = 'partially_refunded';
    public const REFUNDED = 'refunded';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const DISPUTED = 'disputed';

    /** @return list<string> */
    public static function terminal(): array
    {
        return [self::REFUNDED, self::FAILED, self::CANCELLED, self::EXPIRED];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::terminal(), true);
    }
}
