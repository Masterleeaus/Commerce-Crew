<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CheckoutStatus
{
    public const DRAFT = 'draft';
    public const CUSTOMER_DETAILS = 'customer_details';
    public const DELIVERY_SELECTED = 'delivery_selected';
    public const READY = 'ready';
    public const PAYMENT_PENDING = 'payment_pending';
    public const REQUIRES_REVIEW = 'requires_review';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    /** @return list<string> */
    public static function values(): array
    {
        return [
            self::DRAFT,
            self::CUSTOMER_DETAILS,
            self::DELIVERY_SELECTED,
            self::READY,
            self::PAYMENT_PENDING,
            self::REQUIRES_REVIEW,
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
            self::EXPIRED,
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, match ($from) {
            self::DRAFT => [self::CUSTOMER_DETAILS, self::CANCELLED, self::EXPIRED, self::FAILED],
            self::CUSTOMER_DETAILS => [self::DELIVERY_SELECTED, self::CANCELLED, self::EXPIRED, self::FAILED],
            self::DELIVERY_SELECTED => [self::CUSTOMER_DETAILS, self::READY, self::CANCELLED, self::EXPIRED, self::FAILED],
            self::READY => [self::CUSTOMER_DETAILS, self::DELIVERY_SELECTED, self::PAYMENT_PENDING, self::REQUIRES_REVIEW, self::CANCELLED, self::EXPIRED, self::FAILED],
            self::PAYMENT_PENDING => [self::COMPLETED, self::REQUIRES_REVIEW, self::CANCELLED, self::EXPIRED, self::FAILED],
            self::REQUIRES_REVIEW => [self::CUSTOMER_DETAILS, self::DELIVERY_SELECTED, self::READY, self::CANCELLED, self::EXPIRED, self::FAILED],
            default => [],
        }, true);
    }

    public static function isMutable(string $status): bool
    {
        return ! self::isTerminal($status) && $status !== self::PAYMENT_PENDING;
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::COMPLETED, self::FAILED, self::CANCELLED, self::EXPIRED], true);
    }
}
