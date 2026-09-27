<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'System/Contracts/PaymentProvider.php',
    'System/Providers/InternalPaymentProvider.php',
    'System/Services/PaymentRuntime.php',
    'System/Models/PaymentIntent.php',
    'System/Models/PaymentOperation.php',
    'System/Models/PaymentRefund.php',
    'System/Models/PaymentWebhookEvent.php',
    'System/Support/PaymentStatus.php',
    'System/Support/PaymentLifecycle.php',
    'System/Support/PaymentWebhookVerifier.php',
    'System/Http/Controllers/Api/PaymentApiController.php',
    'System/Http/Controllers/Api/PaymentAdminApiController.php',
    'System/Http/Controllers/Api/PaymentWebhookController.php',
    'System/Http/Resources/Api/PaymentIntentResource.php',
    'database/migrations/2026_07_22_060010_payment_engine.php',
];

$tests = 0;
foreach ($required as $file) {
    $tests++;
    if (! is_file($root . '/' . $file)) {
        fwrite(STDERR, "FAIL: missing {$file}\n");
        exit(1);
    }
}

$provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
foreach (['PaymentProvider::class', 'InternalPaymentProvider::class', 'PaymentApiController', 'PaymentAdminApiController', 'PaymentWebhookController', 'payment-intents', 'payment-webhooks'] as $needle) {
    $tests++;
    if (! str_contains($provider, $needle)) {
        fwrite(STDERR, "FAIL: service provider missing {$needle}\n");
        exit(1);
    }
}

$runtime = file_get_contents($root . '/System/Services/PaymentRuntime.php');
foreach (['createForCheckout', 'createForRentalPayment', 'authorize', 'capture', 'cancel', 'refund', 'reconcile', 'processWebhook'] as $method) {
    $tests++;
    if (! str_contains($runtime, 'function ' . $method . '(')) {
        fwrite(STDERR, "FAIL: payment runtime missing {$method}\n");
        exit(1);
    }
}

$health = file_get_contents($root . '/System/Services/ExtensionHealthService.php');
foreach (['payment_engine_tables', 'expired_open_payment_intents', 'captured_payments_amount_mismatch', 'unprocessed_payment_webhooks'] as $needle) {
    $tests++;
    if (! str_contains($health, $needle)) {
        fwrite(STDERR, "FAIL: health service missing {$needle}\n");
        exit(1);
    }
}

$config = file_get_contents($root . '/config/platform-quality.php');
foreach (['payment_engine', "'payments'", 'webhook_tolerance_seconds', 'allowed_methods'] as $needle) {
    $tests++;
    if (! str_contains($config, $needle)) {
        fwrite(STDERR, "FAIL: payment configuration missing {$needle}\n");
        exit(1);
    }
}

echo "Payment contract checks passed: {$tests}\n";
