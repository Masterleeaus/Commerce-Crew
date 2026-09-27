<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$required = [
    'database/migrations/2026_08_03_060014_marketplace_read_mode.php',
    'System/Contracts/MarketplaceReadProvider.php',
    'System/Contracts/MarketplaceTransport.php',
    'System/Models/MarketplaceConnection.php',
    'System/Models/MarketplaceSearch.php',
    'System/Models/MarketplaceSearchResult.php',
    'System/Models/MarketplaceListingSnapshot.php',
    'System/Models/MarketplaceOrderSnapshot.php',
    'System/Models/MarketplaceOrderLineSnapshot.php',
    'System/Models/MarketplaceSyncCursor.php',
    'System/Models/MarketplaceSyncRun.php',
    'System/Providers/AmazonMarketplaceReadProvider.php',
    'System/Providers/EbayMarketplaceReadProvider.php',
    'System/Providers/EtsyMarketplaceReadProvider.php',
    'System/Providers/GenericMarketplaceReadProvider.php',
    'System/Services/MarketplaceProviderRegistry.php',
    'System/Services/MarketplaceReadRuntime.php',
    'System/Services/MarketplaceOrderImportRuntime.php',
    'System/Services/MarketplaceCardRuntime.php',
    'System/Jobs/SearchMarketplaceListings.php',
    'System/Jobs/ImportMarketplaceOrders.php',
    'System/Http/Controllers/Api/MarketplaceApiController.php',
    'System/Http/Controllers/Api/MarketplaceAdminApiController.php',
];
foreach ($required as $file) { $assert(is_file($root . '/' . $file), "missing {$file}"); }

$migration = file_get_contents($root . '/database/migrations/2026_08_03_060014_marketplace_read_mode.php');
foreach (['marketplace_connections','marketplace_searches','marketplace_search_results','marketplace_listing_snapshots','marketplace_order_snapshots','marketplace_order_line_snapshots','marketplace_sync_cursors','marketplace_sync_runs'] as $token) {
    $assert(str_contains($migration, 'ext_chatbot_' . $token), "migration must create {$token}");
}
$assert(str_contains($migration, 'Schema::hasTable'), 'migration is repeat-safe');
$assert(str_contains($migration, "unique(['connection_id', 'external_order_id']"), 'external orders are idempotent per connection');

$connection = file_get_contents($root . '/System/Models/MarketplaceConnection.php');
$assert(str_contains($connection, "'configuration' => 'encrypted:array'"), 'connection configuration is encrypted');
$assert(str_contains($connection, "'credential_reference'"), 'connection stores a credential reference rather than a raw token');
$assert(! str_contains($connection, 'access_token'), 'connection model must not expose access-token fields');
$admin = file_get_contents($root . '/System/Http/Controllers/Api/MarketplaceAdminApiController.php');
$assert(str_contains($admin, 'rejectSecretConfiguration'), 'admin API must reject raw marketplace secrets');
$assert(str_contains($admin, 'credential_reference'), 'admin API must require a vault credential reference');

$registry = file_get_contents($root . '/System/Services/MarketplaceProviderRegistry.php');
foreach (['amazon','ebay','etsy','generic'] as $provider) { $assert(str_contains($registry, "'{$provider}'"), "registry must include {$provider}"); }
$assert(! str_contains($registry, 'publishListing'), 'registry must not expose marketplace writes');

$runtime = file_get_contents($root . '/System/Services/MarketplaceReadRuntime.php');
foreach (['createSearch','executeSearch','cachedSearch','listing'] as $method) { $assert(str_contains($runtime, 'function ' . $method), "search runtime must implement {$method}"); }
$assert(str_contains($runtime, 'search_cache_ttl_seconds'), 'search runtime uses TTL cache configuration');
$assert(str_contains($runtime, 'MarketplaceSearchState::PARTIAL'), 'search runtime supports progressive partial results');

$import = file_get_contents($root . '/System/Services/MarketplaceOrderImportRuntime.php');
foreach (['import','updateOrCreate','MarketplaceSyncCursor','MarketplaceSyncRun'] as $token) { $assert(str_contains($import, $token), "order import must use {$token}"); }
$assert(! str_contains($import, 'CommerceOrder::create'), 'marketplace imports must not silently create native orders');

$provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
foreach (['MarketplaceApiController','MarketplaceAdminApiController','marketplaces/searches','marketplace-connections','marketplace-orders'] as $token) { $assert(str_contains($provider, $token), "service provider must register {$token}"); }

$config = file_get_contents($root . '/config/platform-quality.php');
foreach (['marketplace_read_mode','marketplace_order_import','amazon','ebay','etsy','gateway_url'] as $token) { $assert(str_contains($config, $token), "config must declare {$token}"); }

foreach (['extension.json','index.json','extension.manifest.json'] as $file) {
    $json = json_decode((string) file_get_contents($root . '/' . $file), true);
    $assert(version_compare((string) ($json['version'] ?? '0.0.0'), '4.2.0', '>='), "{$file} must remain compatible with marketplace read mode 4.2.0+");
}
$upgrade = file_get_contents($root . '/upgrade.php');
$assert((bool) preg_match("/'version'=>'([^']+)'/", $upgrade, $match) && version_compare($match[1], '4.2.0', '>='), 'upgrade script must preserve marketplace read mode 4.2.0+');
$assert(str_contains(file_get_contents($root . '/README.md'), 'Marketplace Read Mode'), 'README documents marketplace read mode');
$openapi = file_get_contents($root . '/openapi/conversational-commerce-v1.yaml');
foreach (['/marketplaces/searches','/marketplace-connections','/marketplace-orders'] as $token) { $assert(str_contains($openapi, $token), "OpenAPI must declare {$token}"); }

echo "Marketplace read contract checks passed: {$checks}\n";
