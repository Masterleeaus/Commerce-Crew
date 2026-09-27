<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;

interface ShippingProvider
{
    /** @return list<array<string, mixed>> */
    public function quotes(ChatbotCart $cart, array $address, array $context = []): array;
}
