<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class OrderExceptionDetector
{
    /** @param array<string,mixed> $order @return array<int,array<string,mixed>> */
    public static function detect(array $order, int $nowTimestamp, int $fulfillmentDelaySeconds): array
    {
        $exceptions = [];
        $address = (array) ($order['shipping_address'] ?? []);
        $hasAddress = trim((string) ($address['line1'] ?? $address['address_line_1'] ?? '')) !== ''
            && trim((string) ($address['city'] ?? $address['locality'] ?? '')) !== ''
            && trim((string) ($address['country'] ?? $address['country_code'] ?? '')) !== '';
        if (! $hasAddress && ! in_array((string) ($order['fulfillment_status'] ?? ''), ['digital', 'not_required'], true)) {
            $exceptions[] = self::item('invalid_address', 'high', 'Shipping address is incomplete.');
        }

        if (in_array(strtolower((string) ($order['payment_status'] ?? '')), ['failed', 'declined', 'cancelled', 'chargeback'], true)) {
            $exceptions[] = self::item('payment_failure', 'high', 'The order payment is not in a successful state.');
        }

        $placed = (int) ($order['placed_at_timestamp'] ?? 0);
        $fulfilment = strtolower((string) ($order['fulfillment_status'] ?? ''));
        if ($placed > 0 && $nowTimestamp - $placed > $fulfillmentDelaySeconds && in_array($fulfilment, ['', 'pending', 'unfulfilled', 'processing'], true)) {
            $exceptions[] = self::item('fulfillment_delay', 'warning', 'The order has exceeded the fulfilment delay threshold.');
        }

        if ((bool) ($order['stock_conflict'] ?? false)) {
            $exceptions[] = self::item('stock_conflict', 'critical', 'The order has an unresolved stock conflict.');
        }
        if ((bool) ($order['return_disputed'] ?? false)) {
            $exceptions[] = self::item('disputed_return', 'high', 'A return or refund is disputed.');
        }
        if ((string) ($order['reconciliation_status'] ?? '') === 'variance') {
            $exceptions[] = self::item('settlement_variance', 'warning', 'The reported payout does not reconcile with expected net proceeds.');
        }

        return $exceptions;
    }

    /** @return array<string,mixed> */
    private static function item(string $type, string $severity, string $message): array
    {
        return ['type' => $type, 'severity' => $severity, 'message' => $message];
    }
}
