<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CheckoutAddress
{
    /** @return array<string, string|null> */
    public static function normalise(array $address): array
    {
        $fields = ['name', 'company', 'line1', 'line2', 'city', 'region', 'postcode', 'country'];
        $normalised = [];
        foreach ($fields as $field) {
            $value = $address[$field] ?? null;
            $normalised[$field] = is_scalar($value) ? trim((string) $value) : null;
            if ($normalised[$field] === '') {
                $normalised[$field] = null;
            }
        }
        if ($normalised['country'] !== null) {
            $normalised['country'] = strtoupper($normalised['country']);
        }

        return $normalised;
    }

    public static function isComplete(array $address): bool
    {
        $address = self::normalise($address);
        foreach (['name', 'line1', 'city', 'postcode', 'country'] as $required) {
            if (($address[$required] ?? null) === null) {
                return false;
            }
        }

        return true;
    }
}
