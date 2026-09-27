<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class ShippingZoneMatcher
{
    /** @param array<string, mixed> $address @param array<string, mixed> $rules */
    public static function matches(array $address, array $rules): bool
    {
        $country = strtoupper(trim((string) ($address['country'] ?? '')));
        $region = strtoupper(trim((string) ($address['region'] ?? '')));
        $postcode = strtoupper(preg_replace('/\s+/', '', trim((string) ($address['postcode'] ?? ''))) ?? '');

        $countries = self::normaliseList((array) ($rules['countries'] ?? []));
        if ($countries !== [] && ! in_array($country, $countries, true)) {
            return false;
        }

        $regions = self::normaliseList((array) ($rules['regions'] ?? []));
        if ($regions !== [] && ! in_array($region, $regions, true)) {
            return false;
        }

        $patterns = array_values(array_filter(array_map(
            static fn ($value): string => strtoupper(preg_replace('/\s+/', '', trim((string) $value)) ?? ''),
            (array) ($rules['postcode_patterns'] ?? []),
        )));
        if ($patterns !== [] && ! self::matchesAnyPostcode($postcode, $patterns)) {
            return false;
        }

        return true;
    }

    /** @param list<string> $patterns */
    private static function matchesAnyPostcode(string $postcode, array $patterns): bool
    {
        if ($postcode === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (preg_match('/^(\d+)-(\d+)$/', $pattern, $range) === 1 && ctype_digit($postcode)) {
                $numeric = (int) $postcode;
                if ($numeric >= (int) $range[1] && $numeric <= (int) $range[2]) {
                    return true;
                }
                continue;
            }

            $regex = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/i';
            if (preg_match($regex, $postcode) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function normaliseList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            $values,
        ))));
    }
}
