<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class PaymentEngineTest extends TestCase
{
    public function test_payment_engine_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Contracts/PaymentProvider.php',
            'System/Providers/InternalPaymentProvider.php',
            'System/Services/PaymentRuntime.php',
            'System/Support/PaymentLifecycle.php',
            'System/Support/PaymentWebhookVerifier.php',
            'System/Models/PaymentIntent.php',
            'System/Models/PaymentRefund.php',
            'System/Models/PaymentWebhookEvent.php',
            'System/Http/Controllers/Api/PaymentApiController.php',
            'System/Http/Controllers/Api/PaymentAdminApiController.php',
            'System/Http/Controllers/Api/PaymentWebhookController.php',
            'database/migrations/2026_07_22_060010_payment_engine.php',
        ] as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_payment_runtime_is_connected_to_checkout_and_rental_hire(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/System/Services/PaymentRuntime.php');
        $provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');

        self::assertStringContainsString('createForCheckout', $runtime);
        self::assertStringContainsString('createForRentalPayment', $runtime);
        self::assertStringContainsString('synchroniseCaptured', $runtime);
        self::assertStringContainsString('PaymentProvider::class', $provider);
        self::assertStringContainsString('payment-webhooks', $provider);
    }
}
