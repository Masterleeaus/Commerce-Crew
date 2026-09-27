<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class InventoryAvailability
{
    public static function calculate(
        int $quantity,
        int $reserved = 0,
        int $committed = 0,
        int $damaged = 0,
        int $safetyStock = 0,
    ): int {
        return max(0, $quantity - $reserved - $committed - $damaged - $safetyStock);
    }
}
