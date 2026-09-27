<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

interface TaxProvider
{
    /** @return array<string,mixed>|null */
    public function resolveZone(array $address, array $context = []): ?array;

    /** @return list<array<string,mixed>> */
    public function ratesForLine(array $line, ?array $zone, array $context = []): array;

    /** @return list<array<string,mixed>> */
    public function ratesForShipping(?array $zone, array $context = []): array;
}
