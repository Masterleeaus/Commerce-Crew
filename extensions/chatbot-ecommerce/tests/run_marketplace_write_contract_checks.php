<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$read = static fn (string $path): string => file_get_contents($root . '/' . $path) ?: '';

$required = [
    'System/Contracts/MarketplaceWriteProvider.php',
    'System/Contracts/MarketplaceWriteTransport.php',
    'System/Services/GatewayMarketplaceWriteTransport.php',
    'System/Services/MarketplaceWriteProviderRegistry.php',
    'System/Services/MarketplaceWriteRuntime.php',
    'System/Services/MarketplaceInventoryConflictRuntime.php',
    'System/Models/MarketplaceWriteProposal.php',
    'System/Models/MarketplaceWriteAttempt.php',
    'System/Models/MarketplaceRateLimit.php',
    'System/Jobs/ExecuteMarketplaceWrite.php',
    'System/Http/Resources/Api/MarketplaceWriteProposalResource.php',
    'database/migrations/2026_08_03_060015_marketplace_write_safety.php',
];
foreach ($required as $path) {
    $assert(is_file($root . '/' . $path), "Missing required file: {$path}");
}

$provider = $read('System/ChatbotEcommerceServiceProvider.php');
foreach (['marketplace-write-proposals', "/execute'", "/rollback'"] as $needle) {
    $assert(str_contains($provider, $needle), "Missing route name fragment: {$needle}");
}
$assert(str_contains($provider, 'GatewayMarketplaceWriteTransport'), 'write transport must be container-bound');
$assert(str_contains($provider, 'ExecuteMarketplaceWrite'), 'write job must be registered in provider imports or bindings');

$runtime = $read('System/Services/MarketplaceWriteRuntime.php');
foreach (['prepare(', 'approve(', 'execute(', 'rollback(', 'lockForUpdate', 'expected_source_hash', 'approval_token_hash'] as $needle) {
    $assert(str_contains($runtime, $needle), "Marketplace write runtime missing {$needle}");
}
$assert(str_contains($runtime, 'MarketplaceConflictDetector'), 'write runtime must enforce conflict detection');
$assert(str_contains($runtime, 'MarketplaceRateLimitRuntime'), 'write runtime must enforce rate limits');
$assert(str_contains($runtime, 'source_hash'), 'write runtime must use source versions');

$transport = $read('System/Services/GatewayMarketplaceWriteTransport.php');
$assert(str_contains($transport, '/v1/marketplace/write'), 'write transport must use isolated write endpoint');
$assert(str_contains($transport, 'X-Idempotency-Key'), 'write transport must send idempotency keys');
$assert(str_contains($transport, 'X-Expected-Source-Hash'), 'write transport must send optimistic version');
$assert(str_contains($transport, 'gateway_secret'), 'write transport must sign requests');

$tool = $read('System/Services/MarketplaceToolRuntime.php');
foreach (['seller_marketplace_prepare_write', 'seller_marketplace_approve_write', 'seller_marketplace_write_status', 'seller_marketplace_rollback_write'] as $needle) {
    $assert(str_contains($tool, $needle), "Seller marketplace tool missing {$needle}");
}
$assert(! str_contains($tool, "'seller_marketplace_execute_unapproved'"), 'unapproved execution tool must not exist');

$manifest = json_decode($read('extension.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert($manifest['version'] >= '4.3.0', 'manifest version must be 4.3.0');
foreach (['commerce.marketplace-write-safety', 'commerce.marketplace-write-approvals', 'commerce.marketplace-write-rollback', 'commerce.marketplace-inventory-conflicts'] as $capability) {
    $assert(in_array($capability, $manifest['capabilities'] ?? [], true), "Manifest missing {$capability}");
}
$queueNames = $manifest['queues']['queue_names'] ?? [];
$assert(in_array('chatbot-ecommerce-marketplace-write', $queueNames, true), 'write queue missing from manifest');

$config = $read('config/platform-quality.php');
foreach (['marketplace_write_mode', 'maximum_snapshot_age_seconds', 'maximum_writes_per_minute', 'require_approval', 'allow_bulk_writes'] as $needle) {
    $assert(str_contains($config, $needle), "Marketplace write config missing {$needle}");
}

$openapi = $read('openapi/conversational-commerce-v1.yaml');
foreach (['/marketplace-write-proposals', '/approve', '/execute', '/rollback'] as $needle) {
    $assert(str_contains($openapi, $needle), "OpenAPI missing {$needle}");
}

fwrite(STDOUT, "Marketplace write contracts: {$checks} checks passed.\n");
