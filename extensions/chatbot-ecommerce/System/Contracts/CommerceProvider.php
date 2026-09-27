<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

interface CommerceProvider
{
    public function key(): string;
    public function searchProducts(array $filters = []): iterable;
    public function findProduct(string|int $id): mixed;
    public function quote(array $lines, ?string $couponCode = null): array;
}
