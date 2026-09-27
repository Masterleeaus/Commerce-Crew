<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MarketplaceInventoryConflictResolutionTest extends TestCase
{
    public function test_inventory_reconciliation_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Services/MarketplaceInventoryReconciliationRuntime.php',
            'System/Support/InventoryChannelAllocator.php',
            'System/Support/MarketplaceInventoryReconciliation.php',
            'System/Support/InventoryConflictResolutionPolicy.php',
            'System/Models/MarketplaceInventoryPolicy.php',
            'System/Models/MarketplaceInventoryMapping.php',
            'System/Models/MarketplaceInventoryConflict.php',
            'System/Http/Controllers/Api/MarketplaceInventoryAdminApiController.php',
            'System/Console/Commands/ScanMarketplaceInventoryConflictsCommand.php',
            'database/migrations/2026_08_03_060018_marketplace_inventory_conflict_resolution.php',
        ] as $file) {
            self::assertFileExists($root.'/'.$file, $file.' is required');
        }
    }

    public function test_corrections_reuse_approval_gated_marketplace_writes(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root.'/System/Services/MarketplaceInventoryReconciliationRuntime.php');
        self::assertStringContainsString('MarketplaceWriteRuntime', $runtime);
        self::assertStringContainsString("'update_inventory'", $runtime);
        self::assertStringContainsString('approval_token', $runtime);
        self::assertStringContainsString('never automatically', file_get_contents($root.'/README.md'));
    }

    public function test_equal_share_is_the_safe_default(): void
    {
        $root = dirname(__DIR__, 2);
        $config = file_get_contents($root.'/config/platform-quality.php');
        self::assertStringContainsString("'allocation_mode' => 'equal_share'", $config);
        self::assertStringContainsString("'never_auto_execute' => true", $config);
    }
}
