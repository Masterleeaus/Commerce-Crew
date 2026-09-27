<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CartLineQuantity
{
    public static function normalise(int $quantity): int
    {
        return min(max($quantity, 0), 999);
    }
}
