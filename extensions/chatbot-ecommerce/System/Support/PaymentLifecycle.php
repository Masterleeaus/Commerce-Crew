<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;

final class PaymentLifecycle
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        PaymentStatus::PENDING => [PaymentStatus::REQUIRES_ACTION, PaymentStatus::AUTHORIZED, PaymentStatus::PARTIALLY_CAPTURED, PaymentStatus::CAPTURED, PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED],
        PaymentStatus::REQUIRES_ACTION => [PaymentStatus::PENDING, PaymentStatus::AUTHORIZED, PaymentStatus::PARTIALLY_CAPTURED, PaymentStatus::CAPTURED, PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED],
        PaymentStatus::AUTHORIZED => [PaymentStatus::PARTIALLY_CAPTURED, PaymentStatus::CAPTURED, PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED],
        PaymentStatus::PARTIALLY_CAPTURED => [PaymentStatus::PARTIALLY_CAPTURED, PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED, PaymentStatus::DISPUTED, PaymentStatus::FAILED],
        PaymentStatus::CAPTURED => [PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED, PaymentStatus::DISPUTED],
        PaymentStatus::PARTIALLY_REFUNDED => [PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED, PaymentStatus::DISPUTED],
        PaymentStatus::DISPUTED => [PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED, PaymentStatus::FAILED],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return $from === $to || in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new InvalidArgumentException("Payment status cannot transition from {$from} to {$to}.");
        }
    }

    public static function remainingCapture(int $authorizedOrTotal, int $captured): int
    {
        return max(0, $authorizedOrTotal - $captured);
    }

    public static function remainingRefund(int $captured, int $refunded): int
    {
        return max(0, $captured - $refunded);
    }

    public static function refundStatus(int $captured, int $refunded): string
    {
        return $refunded >= $captured ? PaymentStatus::REFUNDED : PaymentStatus::PARTIALLY_REFUNDED;
    }
}
