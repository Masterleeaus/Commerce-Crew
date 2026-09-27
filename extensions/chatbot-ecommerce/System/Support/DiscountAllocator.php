<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class DiscountAllocator
{
    /**
     * @param list<array{key: string, amount: int}> $eligible
     * @return array<string, int>
     */
    public static function allocate(int $requestedDiscount, array $eligible): array
    {
        $normalised = [];
        foreach ($eligible as $row) {
            $key = (string) ($row['key'] ?? '');
            $amount = max(0, (int) ($row['amount'] ?? 0));
            if ($key !== '' && $amount > 0) {
                $normalised[] = ['key' => $key, 'amount' => $amount];
            }
        }

        $total = array_sum(array_column($normalised, 'amount'));
        $discount = min(max($requestedDiscount, 0), $total);
        $result = [];

        foreach ($normalised as $row) {
            $result[$row['key']] = 0;
        }

        if ($discount === 0 || $total === 0) {
            return $result;
        }

        $allocated = 0;
        foreach ($normalised as $row) {
            $share = intdiv($discount * $row['amount'], $total);
            $share = min($share, $row['amount']);
            $result[$row['key']] = $share;
            $allocated += $share;
        }

        $remainder = $discount - $allocated;
        while ($remainder > 0) {
            $progress = false;
            foreach ($normalised as $row) {
                if ($remainder === 0) {
                    break;
                }
                if ($result[$row['key']] >= $row['amount']) {
                    continue;
                }
                $result[$row['key']]++;
                $remainder--;
                $progress = true;
            }
            if (! $progress) {
                break;
            }
        }

        return $result;
    }
}
