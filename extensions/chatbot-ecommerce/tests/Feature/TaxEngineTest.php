<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class TaxEngineTest extends TestCase
{
    public function test_tax_engine_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Contracts/TaxProvider.php',
            'System/Providers/InternalTaxProvider.php',
            'System/Services/TaxRuntime.php',
            'System/Support/TaxCalculator.php',
            'System/Support/TaxZoneMatcher.php',
            'System/Models/TaxZone.php',
            'System/Models/TaxRate.php',
            'System/Models/TaxExemption.php',
            'System/Http/Controllers/Api/TaxAdminApiController.php',
            'database/migrations/2026_07_22_060008_tax_engine.php',
        ] as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_tax_runtime_is_integrated_with_pricing_and_checkout(): void
    {
        $root = dirname(__DIR__, 2);
        $provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
        $pricing = file_get_contents($root . '/System/Services/PricingRuntime.php');
        $checkout = file_get_contents($root . '/System/Services/CheckoutRuntime.php');

        self::assertStringContainsString('TaxProvider', $provider);
        self::assertStringContainsString('tax/zones', $provider);
        self::assertStringContainsString('tax/rates', $provider);
        self::assertStringContainsString('tax/exemptions', $provider);
        self::assertStringContainsString('TaxRuntime', $pricing);
        self::assertStringContainsString('tax_breakdown', $pricing);
        self::assertStringContainsString('tax_context_hash', $checkout);
    }
}
