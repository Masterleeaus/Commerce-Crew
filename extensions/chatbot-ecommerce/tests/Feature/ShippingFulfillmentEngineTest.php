<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class ShippingFulfillmentEngineTest extends TestCase
{
    public function test_shipping_and_fulfillment_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Contracts/ShippingProvider.php',
            'System/Providers/InternalShippingProvider.php',
            'System/Support/ShippingZoneMatcher.php',
            'System/Support/ShippingRateCalculator.php',
            'System/Support/FulfillmentStatus.php',
            'System/Services/ShippingRuntime.php',
            'System/Services/FulfillmentRuntime.php',
            'System/Http/Controllers/Api/ShippingApiController.php',
            'System/Http/Controllers/Api/ShippingAdminApiController.php',
            'System/Http/Controllers/Api/FulfillmentApiController.php',
            'database/migrations/2026_07_22_060007_shipping_fulfillment_engine.php',
        ] as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_shipping_and_fulfillment_contracts_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
        $shipping = file_get_contents($root . '/System/Services/ShippingRuntime.php');
        $fulfillment = file_get_contents($root . '/System/Services/FulfillmentRuntime.php');

        self::assertStringContainsString('shipping-options', $provider);
        self::assertStringContainsString('shipping/zones', $provider);
        self::assertStringContainsString('fulfillments', $provider);
        self::assertStringContainsString('assertQuoteStillValid', $shipping);
        self::assertStringContainsString('refreshSelectedQuote', $shipping);
        self::assertStringContainsString('summaryForCheckout', $fulfillment);
        self::assertStringContainsString('idempotency_key', $fulfillment);
    }
}
