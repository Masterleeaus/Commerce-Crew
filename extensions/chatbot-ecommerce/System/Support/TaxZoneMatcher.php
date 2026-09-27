<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class TaxZoneMatcher
{
    /** @param array<string, mixed> $address @param array<string, mixed> $zone */
    public static function matches(array $address, array $zone): bool
    {
        $country = strtoupper(trim((string) ($address['country'] ?? '')));
        $region = strtoupper(trim((string) ($address['region'] ?? '')));
        $postcode = strtoupper(preg_replace('/\s+/', '', trim((string) ($address['postcode'] ?? ''))) ?? '');

        $countries = self::normaliseList((array) ($zone['countries'] ?? []), true);
        if ($countries !== [] && ! in_array($country, $countries, true)) {
            return false;
        }

        $regions = self::normaliseList((array) ($zone['regions'] ?? []), true);
        if ($regions !== [] && ! in_array($region, $regions, true)) {
            return false;
        }

        $patterns = self::normaliseList((array) ($zone['postcode_patterns'] ?? []), true);
        if ($patterns !== [] && ! self::postcodeMatchesAny($postcode, $patterns)) {
            return false;
        }

        return true;
    }

    /** @param list<string> $patterns */
    private static function postcodeMatchesAny(string $postcode, array $patterns): bool
    {
        if ($postcode === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if (preg_match('/^([0-9]+)-([0-9]+)$/', $pattern, $matches) === 1 && ctype_digit($postcode)) {
                $value = (int) $postcode;
                if ($value >= (int) $matches[1] && $value <= (int) $matches[2]) {
                    return true;
                }
                continue;
            }

            $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
            if (preg_match($regex, $postcode) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function normaliseList(array $values, bool $uppercase): array
    {
        $normalised = [];
        foreach ($values as $value) {
            $item = trim((string) $value);
            if ($item === '') {
                continue;
            }
            $normalised[] = $uppercase ? strtoupper($item) : $item;
        }

        return array_values(array_unique($normalised));
    }
}
