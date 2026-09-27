<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MarketplaceWriteSafetyTest extends TestCase
{
    public function test_marketplace_write_safety_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Contracts/MarketplaceWriteProvider.php',
            'System/Contracts/MarketplaceWriteTransport.php',
            'System/Services/MarketplaceWriteRuntime.php',
            'System/Services/MarketplaceRateLimitRuntime.php',
            'System/Services/MarketplaceInventoryConflictRuntime.php',
            'System/Models/MarketplaceWriteProposal.php',
            'System/Models/MarketplaceWriteAttempt.php',
            'System/Models/MarketplaceRateLimit.php',
            'System/Jobs/ExecuteMarketplaceWrite.php',
            'database/migrations/2026_08_03_060015_marketplace_write_safety.php',
        ] as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_marketplace_writes_are_approval_and_version_gated(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/System/Services/MarketplaceWriteRuntime.php');
        $transport = file_get_contents($root . '/System/Services/GatewayMarketplaceWriteTransport.php');

        self::assertStringContainsString('MarketplaceWriteApprovalToken::verify', $runtime);
        self::assertStringContainsString('expected_source_hash', $runtime);
        self::assertStringContainsString('MarketplaceConflictDetector::assess', $runtime);
        self::assertStringContainsString('after_source_hash', $runtime);
        self::assertStringContainsString('X-Idempotency-Key', $transport);
        self::assertStringContainsString('/v1/marketplace/write', $transport);
    }

    public function test_bulk_writes_and_financial_marketplace_mutations_are_not_exposed(): void
    {
        $root = dirname(__DIR__, 2);
        $operations = file_get_contents($root . '/System/Support/MarketplaceWriteOperation.php');
        $tools = file_get_contents($root . '/System/Services/MarketplaceToolRuntime.php');

        self::assertStringNotContainsString('refund_order', $operations);
        self::assertStringNotContainsString('cancel_order', $operations);
        self::assertStringNotContainsString('bulk_update', $operations);
        self::assertStringNotContainsString('seller_marketplace_execute_unapproved', $tools);
    }
}
