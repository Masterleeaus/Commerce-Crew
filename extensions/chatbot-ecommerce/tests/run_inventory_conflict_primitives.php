<?php

declare(strict_types=1);

require_once __DIR__.'/../System/Support/InventoryChannelAllocator.php';
require_once __DIR__.'/../System/Support/MarketplaceInventoryReconciliation.php';
require_once __DIR__.'/../System/Support/InventoryConflictResolutionPolicy.php';

use App\Extensions\ChatbotEcommerce\System\Support\InventoryChannelAllocator;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryConflictResolutionPolicy;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceInventoryReconciliation;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$allocation = InventoryChannelAllocator::allocate(20, [
    'buffer_quantity' => 2,
    'allocation_mode' => 'percentage_cap',
    'allocation_bps' => 5000,
    'maximum_quantity' => 7,
]);
$assert($allocation['distributable'] === 18, 'buffer is removed from canonical availability');
$assert($allocation['target_quantity'] === 7, 'percentage allocation respects channel cap');

$zero = InventoryChannelAllocator::allocate(3, ['buffer_quantity' => 5, 'allocation_mode' => 'mirror']);
$assert($zero['target_quantity'] === 0, 'buffer can protect all remaining stock');

$shared = InventoryChannelAllocator::allocate(10, ['allocation_mode' => 'equal_share', 'channel_count' => 3]);
$assert($shared['target_quantity'] === 3, 'safe default divides stock across mapped channels');
$assert($shared['channel_count'] === 3, 'allocation reports the channel count');

$oversell = MarketplaceInventoryReconciliation::assess(
    ['available' => 5, 'version' => 9, 'reserved' => 3, 'committed' => 1],
    ['quantity' => 9, 'observed_at' => '2026-08-03T06:40:00+00:00', 'source_hash' => str_repeat('a', 64)],
    ['target_quantity' => 5, 'maximum_sync_age_seconds' => 600, 'quantity_tolerance' => 0],
    '2026-08-03T06:45:00+00:00'
);
$assert($oversell['code'] === 'external_above_target', 'oversell risk is detected');
$assert($oversell['severity'] === 'critical', 'oversell risk is critical');
$assert($oversell['delta'] === -4, 'correction delta points to target quantity');
$assert($oversell['blocking'] === true, 'oversell risk blocks unsafe automation');

$undersell = MarketplaceInventoryReconciliation::assess(
    ['available' => 12, 'version' => 2, 'reserved' => 0, 'committed' => 0],
    ['quantity' => 4, 'observed_at' => '2026-08-03T06:44:00+00:00', 'source_hash' => str_repeat('b', 64)],
    ['target_quantity' => 10, 'maximum_sync_age_seconds' => 600, 'quantity_tolerance' => 1],
    '2026-08-03T06:45:00+00:00'
);
$assert($undersell['code'] === 'external_below_target', 'undersell is detected');
$assert($undersell['severity'] === 'warning', 'undersell is a warning');
$assert($undersell['delta'] === 6, 'undersell correction delta is positive');

$stale = MarketplaceInventoryReconciliation::assess(
    ['available' => 5, 'version' => 1],
    ['quantity' => 5, 'observed_at' => '2026-08-03T05:00:00+00:00', 'source_hash' => str_repeat('c', 64)],
    ['target_quantity' => 5, 'maximum_sync_age_seconds' => 300],
    '2026-08-03T06:45:00+00:00'
);
$assert($stale['code'] === 'external_state_stale', 'stale marketplace observation is detected');
$assert($stale['blocking'] === true, 'stale state blocks correction preparation');

$balanced = MarketplaceInventoryReconciliation::assess(
    ['available' => 7, 'version' => 3],
    ['quantity' => 7, 'observed_at' => '2026-08-03T06:44:00+00:00', 'source_hash' => str_repeat('d', 64)],
    ['target_quantity' => 7, 'maximum_sync_age_seconds' => 300],
    '2026-08-03T06:45:00+00:00'
);
$assert($balanced['code'] === 'in_sync', 'matching quantities are in sync');
$assert($balanced['conflict'] === false, 'matching quantities create no conflict');

$eligibility = InventoryConflictResolutionPolicy::evaluate([
    'resolution_mode' => 'auto_prepare',
    'maximum_auto_delta' => 5,
    'require_fresh_snapshot' => true,
], $undersell);
$assert($eligibility['auto_prepare'] === false, 'large automatic correction is refused');

$small = $undersell;
$small['delta'] = 2;
$small['blocking'] = false;
$small['snapshot_fresh'] = true;
$eligibility = InventoryConflictResolutionPolicy::evaluate([
    'resolution_mode' => 'auto_prepare',
    'maximum_auto_delta' => 5,
    'require_fresh_snapshot' => true,
], $small);
$assert($eligibility['auto_prepare'] === true, 'small fresh correction may be automatically prepared');
$assert($eligibility['auto_execute'] === false, 'automatic execution is never permitted');

fwrite(STDOUT, "Inventory conflict primitive checks passed: {$checks}\n");
