<?php

declare(strict_types=1);

require_once __DIR__.'/../System/Support/MarketplaceBulkImpact.php';
require_once __DIR__.'/../System/Support/MarketplaceBulkSelector.php';

use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceBulkImpact;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceBulkSelector;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$impact = MarketplaceBulkImpact::priceChange(10000, ['mode' => 'percentage', 'value' => 5]);
$assert($impact['after'] === 10500, 'percentage increase uses integer minor units');
$assert($impact['delta'] === 500, 'percentage delta is exact');
$impact = MarketplaceBulkImpact::priceChange(10001, ['mode' => 'percentage', 'value' => -10]);
$assert($impact['after'] === 9001, 'percentage rounding is deterministic');
$impact = MarketplaceBulkImpact::priceChange(10000, ['mode' => 'fixed_delta', 'value' => -1500]);
$assert($impact['after'] === 8500, 'fixed delta is supported');
$impact = MarketplaceBulkImpact::priceChange(10000, ['mode' => 'set', 'value' => 12000]);
$assert($impact['after'] === 12000, 'absolute set is supported');

$listings = [
    ['external_listing_id' => 'A', 'provider' => 'ebay', 'status' => 'active', 'brand' => 'Acme', 'category' => 'vacuum', 'price_minor' => 10000],
    ['external_listing_id' => 'B', 'provider' => 'ebay', 'status' => 'paused', 'brand' => 'Acme', 'category' => 'vacuum', 'price_minor' => 12000],
    ['external_listing_id' => 'C', 'provider' => 'amazon', 'status' => 'active', 'brand' => 'Other', 'category' => 'camera', 'price_minor' => 50000],
];
$selected = MarketplaceBulkSelector::filter($listings, ['provider' => ['ebay'], 'status' => ['active'], 'brand' => ['Acme']]);
$assert(count($selected) === 1 && $selected[0]['external_listing_id'] === 'A', 'selector combines filters');
$selected = MarketplaceBulkSelector::filter($listings, ['external_listing_ids' => ['B', 'C']]);
$assert(count($selected) === 2, 'selector accepts explicit listing ids');
$selected = MarketplaceBulkSelector::filter($listings, ['minimum_price_minor' => 11000, 'maximum_price_minor' => 20000]);
$assert(count($selected) === 1 && $selected[0]['external_listing_id'] === 'B', 'selector applies price range');

fwrite(STDOUT, "Marketplace bulk primitive checks passed: {$checks}\n");
