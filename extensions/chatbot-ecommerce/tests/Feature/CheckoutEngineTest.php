<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class CheckoutEngineTest extends TestCase
{
    public function test_checkout_engine_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Support/CheckoutStatus.php',
            'System/Support/CheckoutAddress.php',
            'System/Models/CheckoutSession.php',
            'System/Models/CheckoutOperation.php',
            'System/Services/CheckoutRuntime.php',
            'System/Http/Controllers/Api/CheckoutApiController.php',
            'System/Http/Resources/Api/CheckoutSessionResource.php',
            'System/Console/Commands/ExpireCheckoutSessions.php',
            'database/migrations/2026_07_22_060006_checkout_engine.php',
        ] as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_checkout_routes_and_safety_contracts_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
        $runtime = file_get_contents($root . '/System/Services/CheckoutRuntime.php');

        self::assertStringContainsString('checkout/prepare', $provider);
        self::assertStringContainsString('checkout/approve', $provider);
        self::assertStringContainsString('checkout/cancel', $provider);
        self::assertStringContainsString('expectedPricingHash', $runtime);
        self::assertStringContainsString('approval_token_hash', $runtime);
        self::assertStringContainsString('payment_attempt_key', $runtime);
        self::assertStringContainsString('completion_key', $runtime);
        self::assertStringContainsString('assertReservationCoverage', $runtime);
    }
}
