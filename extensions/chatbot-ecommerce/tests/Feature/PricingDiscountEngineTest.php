<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class PricingDiscountEngineTest extends TestCase
{
    public function test_pricing_engine_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        $required = [
            'System/Models/PricingRule.php',
            'System/Models/PricingSnapshot.php',
            'System/Models/CouponUsage.php',
            'System/Services/CouponRuntime.php',
            'System/Support/MoneyMath.php',
            'System/Support/DiscountAllocator.php',
            'System/Http/Controllers/Api/PricingAdminApiController.php',
            'database/migrations/2026_07_22_060005_pricing_discount_engine.php',
        ];

        foreach ($required as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_pricing_routes_are_registered(): void
    {
        $provider = file_get_contents(dirname(__DIR__, 2) . '/System/ChatbotEcommerceServiceProvider.php');

        self::assertStringContainsString("pricing/rules", $provider);
        self::assertStringContainsString("pricing/coupons", $provider);
        self::assertStringContainsString("pricing/snapshots", $provider);
    }

    public function test_extension_version_is_advanced(): void
    {
        $metadata = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/extension.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue(version_compare((string) $metadata['version'], '3.3.0', '>='));
    }
}
