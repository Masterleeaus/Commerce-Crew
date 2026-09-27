<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;

final class MarketplaceBulkImpact
{
    /** @param array{mode:string,value:int|float|string} $change
     *  @return array{before:int,after:int,delta:int}
     */
    public static function priceChange(int $before, array $change): array
    {
        $mode = strtolower(trim((string) ($change['mode'] ?? '')));
        $value = (float) ($change['value'] ?? 0);
        $after = match ($mode) {
            'percentage' => (int) round($before * (1 + ($value / 100)), 0, PHP_ROUND_HALF_UP),
            'fixed_delta' => $before + (int) round($value, 0, PHP_ROUND_HALF_UP),
            'set' => (int) round($value, 0, PHP_ROUND_HALF_UP),
            default => throw new InvalidArgumentException("Unsupported bulk price mode: {$mode}"),
        };
        if ($after < 0) {
            throw new InvalidArgumentException('Bulk price changes cannot create a negative price.');
        }

        return ['before' => $before, 'after' => $after, 'delta' => $after - $before];
    }
}
