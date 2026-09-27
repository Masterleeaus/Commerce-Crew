<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/System/Support/BnplInstallmentSchedule.php';
require $root . '/System/Support/BnplStatus.php';
require $root . '/System/Support/BnplEligibility.php';
require $root . '/System/Support/BnplStateToken.php';
require $root . '/System/Support/ShoppingMode.php';

use App\Extensions\ChatbotEcommerce\System\Support\BnplEligibility;
use App\Extensions\ChatbotEcommerce\System\Support\BnplInstallmentSchedule;
use App\Extensions\ChatbotEcommerce\System\Support\BnplStateToken;
use App\Extensions\ChatbotEcommerce\System\Support\ShoppingMode;

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$schedule = BnplInstallmentSchedule::build(1001, 4, 14, '2026-08-03');
$assert(count($schedule) === 4, 'four instalments are generated');
$assert(array_sum(array_column($schedule, 'amount')) === 1001, 'instalments preserve the exact total');
$assert($schedule[0]['amount'] === 251, 'rounding remainder is applied deterministically');
$assert($schedule[3]['due_on'] === '2026-09-14', 'interval dates are generated deterministically');

$profile = [
    'active' => true,
    'supported_scopes' => ['checkout', 'rental_hire'],
    'supported_shopping_modes' => [ShoppingMode::NATIVE, ShoppingMode::MARKETPLACE_ASSISTED],
    'supported_account_types' => ['hire'],
    'supported_currencies' => ['AUD'],
    'supported_countries' => ['AU'],
    'minimum_amount' => 1000,
    'maximum_amount' => 100000,
    'installment_counts' => [4],
    'licence_reference' => 'ACL-TEST',
    'terms_url' => 'https://provider.example/terms',
    'hardship_url' => 'https://provider.example/hardship',
    'complaints_url' => 'https://provider.example/complaints',
];

$eligible = BnplEligibility::evaluate($profile, 5000, 'AUD', 'AU', 'checkout', ShoppingMode::NATIVE, null, true);
$assert($eligible['eligible'] === true, 'native checkout is eligible when policy matches');
$assert($eligible['display_only'] === false, 'native checkout produces actionable offers');

$marketplace = BnplEligibility::evaluate($profile, 5000, 'AUD', 'AU', 'checkout', ShoppingMode::MARKETPLACE_ASSISTED, null, true);
$assert($marketplace['eligible'] === false, 'marketplace-assisted checkout is not processed natively');
$assert($marketplace['display_only'] === true, 'marketplace-assisted mode exposes marketplace-managed BNPL only');

$hire = BnplEligibility::evaluate($profile, 5000, 'AUD', 'AU', 'rental_hire', ShoppingMode::NATIVE, 'hire', true);
$assert($hire['eligible'] === true, 'hire payments can use BNPL when enabled');
$rent = BnplEligibility::evaluate($profile, 5000, 'AUD', 'AU', 'rental_hire', ShoppingMode::NATIVE, 'rent', true);
$assert($rent['eligible'] === false, 'rent is denied when the provider profile does not allow it');

$unlicensed = $profile;
$unlicensed['licence_reference'] = null;
$assert(BnplEligibility::evaluate($unlicensed, 5000, 'AUD', 'AU', 'checkout', ShoppingMode::NATIVE, null, true)['eligible'] === false, 'licensed-provider requirement fails closed');

$token = BnplStateToken::issue(['offer_uuid' => 'offer-1', 'amount' => 5000], 'test-secret', 1785720540, 900);
$decoded = BnplStateToken::verify($token, 'test-secret', 1785720540);
$assert(($decoded['offer_uuid'] ?? null) === 'offer-1', 'signed BNPL state token round-trips');
$assert(BnplStateToken::verify($token, 'wrong-secret', 1785720540) === null, 'state token rejects the wrong secret');
$assert(BnplStateToken::verify($token, 'test-secret', 1785721441) === null, 'expired state token is rejected');

echo "BNPL primitive checks passed: {$tests}\n";
