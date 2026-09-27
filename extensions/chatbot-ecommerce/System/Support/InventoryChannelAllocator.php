<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;

final class InventoryChannelAllocator
{
    /**
     * @param array<string,mixed> $policy
     * @return array{canonical_available:int,protected_buffer:int,distributable:int,allocation_mode:string,target_quantity:int}
     */
    public static function allocate(int $canonicalAvailable, array $policy = []): array
    {
        $canonicalAvailable = max(0, $canonicalAvailable);
        $buffer = max(0, (int) ($policy['buffer_quantity'] ?? 0));
        $distributable = max(0, $canonicalAvailable - $buffer);
        $mode = strtolower(trim((string) ($policy['allocation_mode'] ?? 'equal_share')));
        $maximum = array_key_exists('maximum_quantity', $policy) && $policy['maximum_quantity'] !== null
            ? max(0, (int) $policy['maximum_quantity'])
            : null;

        $target = match ($mode) {
            'mirror' => $distributable,
            'equal_share' => intdiv($distributable, max(1, (int) ($policy['channel_count'] ?? 1))),
            'percentage' => self::percentage($distributable, (int) ($policy['allocation_bps'] ?? 10000)),
            'fixed_cap' => min($distributable, max(0, (int) ($policy['fixed_quantity'] ?? $maximum ?? 0))),
            'percentage_cap' => self::percentage($distributable, (int) ($policy['allocation_bps'] ?? 10000)),
            default => throw new InvalidArgumentException('Unsupported inventory allocation mode.'),
        };

        if ($maximum !== null) {
            $target = min($target, $maximum);
        }

        $minimum = max(0, (int) ($policy['minimum_quantity'] ?? 0));
        if ($target > 0 && $target < $minimum) {
            $target = 0;
        }

        return [
            'canonical_available' => $canonicalAvailable,
            'protected_buffer' => $buffer,
            'distributable' => $distributable,
            'allocation_mode' => $mode,
            'channel_count' => max(1, (int) ($policy['channel_count'] ?? 1)),
            'target_quantity' => max(0, $target),
        ];
    }

    private static function percentage(int $quantity, int $basisPoints): int
    {
        $basisPoints = min(10000, max(0, $basisPoints));

        return intdiv($quantity * $basisPoints, 10000);
    }
}
