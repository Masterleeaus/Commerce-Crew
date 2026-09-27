<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

interface InventoryProvider
{
    public function available(int $variantId): int;
    public function reserve(int $variantId, int $quantity, string $reference): void;
    public function release(string $reference): void;
}
