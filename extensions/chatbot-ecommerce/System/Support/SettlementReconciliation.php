<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class SettlementReconciliation
{
    /** @param array<int,array<string,mixed>> $entries @return array<string,int|string> */
    public static function calculate(array $entries): array
    {
        $gross = $fees = $tax = $shipping = $refunds = $chargebacks = $reported = $adjustments = 0;

        foreach ($entries as $entry) {
            $type = strtolower((string) ($entry['type'] ?? 'adjustment'));
            $amount = (int) ($entry['amount'] ?? 0);
            match ($type) {
                'sale', 'gross', 'order' => $gross += $amount,
                'fee', 'fees', 'commission', 'processing_fee' => $fees += abs($amount),
                'tax', 'withheld_tax' => $tax += abs($amount),
                'shipping_cost', 'shipping_fee', 'postage' => $shipping += abs($amount),
                'refund', 'refunded' => $refunds += abs($amount),
                'chargeback', 'dispute' => $chargebacks += abs($amount),
                'payout', 'net_payout', 'deposit' => $reported += $amount,
                default => $adjustments += $amount,
            };
        }

        $expected = $gross - $fees - $tax - $shipping - $refunds - $chargebacks + $adjustments;
        $variance = $reported - $expected;

        return [
            'gross_amount' => $gross,
            'fee_amount' => $fees,
            'tax_withheld_amount' => $tax,
            'shipping_cost_amount' => $shipping,
            'refund_amount' => $refunds,
            'chargeback_amount' => $chargebacks,
            'adjustment_amount' => $adjustments,
            'expected_net_amount' => $expected,
            'reported_net_amount' => $reported,
            'variance_amount' => $variance,
            'status' => $reported === 0 ? 'awaiting_payout' : ($variance === 0 ? 'reconciled' : 'variance'),
        ];
    }
}
