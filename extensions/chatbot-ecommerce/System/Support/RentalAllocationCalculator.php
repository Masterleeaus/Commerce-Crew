<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;

final class RentalAllocationCalculator
{
    /**
     * @param list<array{uuid:string,balance_due:int,due_date?:string,id?:int}> $charges
     * @return array{allocations:list<array{charge_uuid:string,amount:int}>,allocated_total:int,unallocated:int}
     */
    public static function allocate(int $paymentAmount, array $charges): array
    {
        if ($paymentAmount < 0) {
            throw new InvalidArgumentException('Payment amount cannot be negative.');
        }

        usort($charges, static function (array $left, array $right): int {
            $date = strcmp((string) ($left['due_date'] ?? ''), (string) ($right['due_date'] ?? ''));
            if ($date !== 0) {
                return $date;
            }
            return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        });

        $remaining = $paymentAmount;
        $allocations = [];
        foreach ($charges as $charge) {
            if ($remaining <= 0) {
                break;
            }
            $balance = max(0, (int) ($charge['balance_due'] ?? 0));
            $uuid = trim((string) ($charge['uuid'] ?? ''));
            if ($balance === 0 || $uuid === '') {
                continue;
            }
            $amount = min($remaining, $balance);
            $allocations[] = ['charge_uuid' => $uuid, 'amount' => $amount];
            $remaining -= $amount;
        }

        return [
            'allocations' => $allocations,
            'allocated_total' => $paymentAmount - $remaining,
            'unallocated' => $remaining,
        ];
    }
}
