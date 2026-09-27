<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class BnplPaymentEngineTest extends TestCase
{
    public function test_bnpl_engine_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Services/BnplRuntime.php',
            'System/Models/BnplProviderProfile.php',
            'System/Models/BnplOffer.php',
            'System/Support/BnplEligibility.php',
            'System/Support/BnplInstallmentSchedule.php',
            'System/Http/Controllers/Api/BnplApiController.php',
            'System/Http/Controllers/Api/BnplAdminApiController.php',
            'database/migrations/2026_08_03_060011_bnpl_engine.php',
        ] as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_bnpl_preserves_native_and_marketplace_assisted_modes(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/System/Services/BnplRuntime.php');
        $eligibility = file_get_contents($root . '/System/Support/BnplEligibility.php');

        self::assertStringContainsString('ShoppingMode::NATIVE', $runtime);
        self::assertStringContainsString('ShoppingMode::MARKETPLACE_ASSISTED', $eligibility);
        self::assertStringContainsString('marketplace_managed', $eligibility);
    }
}
