<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/System/Support/TaxZoneMatcher.php';
require $root . '/System/Support/TaxCalculator.php';

use App\Extensions\ChatbotEcommerce\System\Support\TaxCalculator;
use App\Extensions\ChatbotEcommerce\System\Support\TaxZoneMatcher;

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$address = ['country' => 'AU', 'region' => 'VIC', 'postcode' => '3064'];
$assert(TaxZoneMatcher::matches($address, ['countries' => ['AU']]), 'country zone matches');
$assert(TaxZoneMatcher::matches($address, ['countries' => ['AU'], 'regions' => ['VIC']]), 'region zone matches');
$assert(TaxZoneMatcher::matches($address, ['postcode_patterns' => ['30*']]), 'postcode wildcard matches');
$assert(! TaxZoneMatcher::matches($address, ['countries' => ['NZ']]), 'different country does not match');

$exclusive = TaxCalculator::calculate(10000, [
    ['code' => 'GST', 'name' => 'GST', 'rate_bps' => 1000, 'compound' => false],
], false);
$assert($exclusive['base_amount'] === 10000, 'exclusive base remains unchanged');
$assert($exclusive['tax_total'] === 1000, 'exclusive tax is calculated');
$assert($exclusive['total'] === 11000, 'exclusive total includes tax');

$inclusive = TaxCalculator::calculate(11000, [
    ['code' => 'GST', 'name' => 'GST', 'rate_bps' => 1000, 'compound' => false],
], true);
$assert($inclusive['base_amount'] === 10000, 'inclusive base is extracted');
$assert($inclusive['tax_total'] === 1000, 'inclusive tax is extracted');
$assert($inclusive['total'] === 11000, 'inclusive total remains unchanged');

$multi = TaxCalculator::calculate(10000, [
    ['code' => 'STATE', 'name' => 'State tax', 'rate_bps' => 500, 'compound' => false],
    ['code' => 'LOCAL', 'name' => 'Local tax', 'rate_bps' => 250, 'compound' => false],
], false);
$assert($multi['tax_total'] === 750, 'multiple non-compound components sum deterministically');
$assert(count($multi['components']) === 2, 'multiple tax components are retained');

$compound = TaxCalculator::calculate(10000, [
    ['code' => 'BASE', 'name' => 'Base tax', 'rate_bps' => 1000, 'compound' => false],
    ['code' => 'SURCHARGE', 'name' => 'Surcharge', 'rate_bps' => 500, 'compound' => true],
], false);
$assert($compound['tax_total'] === 1550, 'compound component taxes prior tax');
$assert($compound['total'] === 11550, 'compound total is correct');

$exempt = TaxCalculator::calculate(10000, [], false);
$assert($exempt['tax_total'] === 0 && $exempt['total'] === 10000, 'empty component list is tax free');

$assert(TaxCalculator::normaliseRateBps(-1) === 0, 'negative tax rate is clamped');
$assert(TaxCalculator::normaliseRateBps(1000000) === 100000, 'tax rate has a safe upper bound');

echo "Tax primitive checks passed: {$tests}\n";
