<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class BnplStatus
{
    public const QUOTED = 'quoted';
    public const MARKETPLACE_MANAGED = 'marketplace_managed';
    public const REQUIRES_ACTION = 'requires_action';
    public const APPROVED = 'approved';
    public const CAPTURED = 'captured';
    public const PARTIALLY_REFUNDED = 'partially_refunded';
    public const REFUNDED = 'refunded';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    /** @return list<string> */
    public static function terminal(): array
    {
        return [self::CAPTURED, self::REFUNDED, self::DECLINED, self::CANCELLED, self::EXPIRED];
    }
}
