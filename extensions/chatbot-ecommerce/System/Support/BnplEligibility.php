<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class BnplEligibility
{
    /**
     * @param array<string,mixed> $profile
     * @return array{eligible:bool,display_only:bool,availability:string,reason:?string}
     */
    public static function evaluate(
        array $profile,
        int $amount,
        string $currency,
        ?string $country,
        string $scope,
        string $shoppingMode,
        ?string $accountType,
        bool $requireLicensedProvider,
    ): array {
        $shoppingMode = ShoppingMode::normalize($shoppingMode);
        $currency = strtoupper(trim($currency));
        $country = strtoupper(trim((string) $country));
        $scope = strtolower(trim($scope));
        $accountType = strtolower(trim((string) $accountType));

        if (! (bool) ($profile['active'] ?? false)) {
            return self::deny('provider_inactive');
        }
        if (! in_array($scope, self::strings($profile['supported_scopes'] ?? []), true)) {
            return self::deny('scope_not_supported');
        }
        if (! in_array($shoppingMode, self::strings($profile['supported_shopping_modes'] ?? []), true)) {
            return self::deny('shopping_mode_not_supported');
        }
        if ($requireLicensedProvider && trim((string) ($profile['licence_reference'] ?? '')) === '') {
            return self::deny('licensed_provider_required');
        }
        if ($requireLicensedProvider && (
            trim((string) ($profile['terms_url'] ?? '')) === ''
            || trim((string) ($profile['hardship_url'] ?? '')) === ''
            || trim((string) ($profile['complaints_url'] ?? '')) === ''
        )) {
            return self::deny('provider_disclosures_required');
        }
        if ($amount < max(1, (int) ($profile['minimum_amount'] ?? 1))) {
            return self::deny('amount_below_minimum');
        }
        $maximum = (int) ($profile['maximum_amount'] ?? 0);
        if ($maximum > 0 && $amount > $maximum) {
            return self::deny('amount_above_maximum');
        }

        $currencies = array_map('strtoupper', self::strings($profile['supported_currencies'] ?? []));
        if ($currencies !== [] && ! in_array($currency, $currencies, true)) {
            return self::deny('currency_not_supported');
        }

        $countries = array_map('strtoupper', self::strings($profile['supported_countries'] ?? []));
        if ($countries !== [] && ($country === '' || ! in_array($country, $countries, true))) {
            return self::deny('country_not_supported');
        }

        if ($scope === 'rental_hire') {
            $accountTypes = self::strings($profile['supported_account_types'] ?? []);
            if ($accountTypes !== [] && ! in_array($accountType, $accountTypes, true)) {
                return self::deny('account_type_not_supported');
            }
        }

        if ($shoppingMode === ShoppingMode::MARKETPLACE_ASSISTED) {
            return [
                'eligible' => false,
                'display_only' => true,
                'availability' => BnplStatus::MARKETPLACE_MANAGED,
                'reason' => 'marketplace_checkout_owned',
            ];
        }

        return [
            'eligible' => true,
            'display_only' => false,
            'availability' => 'available',
            'reason' => null,
        ];
    }

    /** @return array{eligible:false,display_only:false,availability:string,reason:string} */
    private static function deny(string $reason): array
    {
        return [
            'eligible' => false,
            'display_only' => false,
            'availability' => 'unavailable',
            'reason' => $reason,
        ];
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $values,
        ), static fn (string $value): bool => $value !== '')));
    }
}
