<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/System/Support/ShippingZoneMatcher.php';
require $root . '/System/Support/ShippingRateCalculator.php';
require $root . '/System/Support/FulfillmentStatus.php';

use App\Extensions\ChatbotEcommerce\System\Support\FulfillmentStatus;
use App\Extensions\ChatbotEcommerce\System\Support\ShippingRateCalculator;
use App\Extensions\ChatbotEcommerce\System\Support\ShippingZoneMatcher;

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$address = ['country' => 'AU', 'region' => 'VIC', 'postcode' => '3064'];
$assert(ShippingZoneMatcher::matches($address, ['countries' => ['AU']]), 'country zone matches');
$assert(ShippingZoneMatcher::matches($address, ['countries' => ['AU'], 'regions' => ['VIC']]), 'region zone matches');
$assert(ShippingZoneMatcher::matches($address, ['postcode_patterns' => ['30*']]), 'postcode wildcard matches');
$assert(ShippingZoneMatcher::matches($address, ['postcode_patterns' => ['3000-3999']]), 'postcode range matches');
$assert(! ShippingZoneMatcher::matches($address, ['countries' => ['NZ']]), 'different country does not match');

$assert(ShippingRateCalculator::calculate(['rate_type' => 'flat', 'amount' => 1295], 5000, 0) === 1295, 'flat rate is returned');
$assert(ShippingRateCalculator::calculate(['rate_type' => 'flat', 'amount' => 1295, 'free_above' => 5000], 5000, 0) === 0, 'free threshold is honoured');
$assert(ShippingRateCalculator::calculate(['rate_type' => 'weight', 'base_amount' => 500, 'per_kg_amount' => 250], 0, 2500) === 1250, 'weight rate rounds by kilogram');
$assert(ShippingRateCalculator::calculate(['rate_type' => 'subtotal', 'tiers' => [['min' => 0, 'max' => 4999, 'amount' => 1000], ['min' => 5000, 'amount' => 0]]], 7000, 0) === 0, 'subtotal tier is selected');

$assert(FulfillmentStatus::canTransition('pending', 'processing'), 'pending can process');
$assert(FulfillmentStatus::canTransition('processing', 'shipped'), 'processing can ship');
$assert(FulfillmentStatus::canTransition('shipped', 'delivered'), 'shipped can deliver');
$assert(! FulfillmentStatus::canTransition('delivered', 'processing'), 'delivered is terminal');
$assert(FulfillmentStatus::aggregate(5, 0, 0) === 'unfulfilled', 'zero fulfilled is unfulfilled');
$assert(FulfillmentStatus::aggregate(5, 2, 0) === 'partially_fulfilled', 'partial quantity is partial');
$assert(FulfillmentStatus::aggregate(5, 5, 0) === 'fulfilled', 'all shipped is fulfilled');
$assert(FulfillmentStatus::aggregate(5, 5, 5) === 'delivered', 'all delivered is delivered');

echo "Shipping and fulfillment primitive checks passed: {$tests}\n";
