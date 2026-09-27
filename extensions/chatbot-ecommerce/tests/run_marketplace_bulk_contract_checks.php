<?php

declare(strict_types=1);

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};
$files = [
    'System/Services/MarketplaceBulkRuntime.php',
    'System/Models/MarketplaceBulkBatch.php',
    'System/Models/MarketplaceBulkItem.php',
    'System/Http/Controllers/Api/MarketplaceBulkAdminApiController.php',
    'System/Http/Resources/Api/MarketplaceBulkBatchResource.php',
    'System/Support/MarketplaceBulkImpact.php',
    'System/Support/MarketplaceBulkSelector.php',
    'database/migrations/2026_08_03_060016_marketplace_bulk_operations.php',
];
foreach ($files as $file) $assert(is_file(__DIR__.'/../'.$file), "{$file} exists");
$provider = file_get_contents(__DIR__.'/../System/ChatbotEcommerceServiceProvider.php');
foreach (['MarketplaceBulkAdminApiController', 'marketplace-bulk-batches', "'preview'", "'approve'", "'execute'", "'rollback'"] as $needle) $assert(str_contains($provider, $needle), "provider contains {$needle}");
$runtime = file_get_contents(__DIR__.'/../System/Services/MarketplaceBulkRuntime.php');
foreach (['allow_bulk_writes', 'MarketplaceBulkSelector::filter', 'MarketplaceBulkImpact::priceChange', 'approveFromBatch', 'PARTIALLY_COMPLETED', 'rollback_failed', 'idempotency_key'] as $needle) $assert(str_contains($runtime, $needle), "runtime contains {$needle}");
$writeRuntime = file_get_contents(__DIR__.'/../System/Services/MarketplaceWriteRuntime.php');
$assert(str_contains($writeRuntime, 'approveFromBatch'), 'single-write runtime supports governed batch approval');
$manifest = json_decode(file_get_contents(__DIR__.'/../extension.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(in_array('commerce.marketplace-bulk-operations', $manifest['capabilities'] ?? [], true), 'manifest declares bulk operations');
$assert(in_array('ext_chatbot_marketplace_bulk_batches', $manifest['database']['owned_tables'] ?? [], true), 'manifest owns batch table');
$assert(in_array('ext_chatbot_marketplace_bulk_items', $manifest['database']['owned_tables'] ?? [], true), 'manifest owns item table');
fwrite(STDOUT, "Marketplace bulk contract checks passed: {$checks}\n");
