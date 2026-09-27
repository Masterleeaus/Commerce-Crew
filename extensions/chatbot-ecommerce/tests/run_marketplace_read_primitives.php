<?php

declare(strict_types=1);

require __DIR__ . '/../System/Support/MarketplaceCapability.php';
require __DIR__ . '/../System/Support/MarketplaceSearchState.php';
require __DIR__ . '/../System/Support/MarketplaceCacheKey.php';
require __DIR__ . '/../System/Support/MarketplaceResultNormalizer.php';

use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceCapability;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceSearchState;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceCacheKey;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceResultNormalizer;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$assert(MarketplaceCapability::readOnly() === ['search_listings','get_listing','get_inventory','import_orders'], 'read-only capabilities are stable');
$assert(! MarketplaceCapability::isAllowed('publish_listing'), 'write capabilities are denied');
$assert(MarketplaceSearchState::canTransition('queued', 'searching'), 'queued searches may start');
$assert(MarketplaceSearchState::canTransition('searching', 'partial'), 'searches may progressively render partial results');
$assert(! MarketplaceSearchState::canTransition('completed', 'searching'), 'completed searches cannot restart');

$keyA = MarketplaceCacheKey::make(10, 'vacuum', ['max_price' => 50000, 'colour' => 'red'], ['ebay','amazon']);
$keyB = MarketplaceCacheKey::make(10, ' vacuum ', ['colour' => 'red', 'max_price' => 50000], ['amazon','ebay']);
$assert($keyA === $keyB && strlen($keyA) === 64, 'cache key is canonical and SHA-256');

$normal = MarketplaceResultNormalizer::listing('ebay', [
    'id' => 'ABC-1', 'title' => 'Vacuum', 'price' => ['amount' => 12999, 'currency' => 'aud'],
    'url' => 'https://example.test/item', 'images' => ['https://example.test/image.jpg'],
    'availability' => 'in_stock', 'seller' => ['name' => 'Example Seller'],
]);
$assert($normal['provider'] === 'ebay' && $normal['external_listing_id'] === 'ABC-1', 'listing identity is normalized');
$assert($normal['price_amount'] === 12999 && $normal['currency'] === 'AUD', 'money is normalized in minor units');
$assert($normal['image_url'] === 'https://example.test/image.jpg', 'first image is normalized');
$assert(! array_key_exists('access_token', $normal), 'secrets are never copied into normalized listings');

$order = MarketplaceResultNormalizer::order('amazon', [
    'id' => 'ORDER-1', 'status' => 'unshipped', 'currency' => 'AUD', 'total' => 4500,
    'buyer' => ['display_name' => 'Customer'], 'lines' => [['id' => 'L1', 'sku' => 'SKU-1', 'title' => 'Item', 'quantity' => 1, 'unit_amount' => 4500]],
]);
$assert($order['external_order_id'] === 'ORDER-1' && count($order['lines']) === 1, 'orders and lines are normalized');
$assert(! isset($order['buyer']['email']), 'buyer PII is minimized by default');

echo "Marketplace read primitive checks passed: {$checks}\n";
