<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'System/Models/CheckoutSession.php',
    'System/Models/CheckoutOperation.php',
    'System/Services/CheckoutRuntime.php',
    'System/Http/Controllers/Api/CheckoutApiController.php',
    'System/Http/Resources/Api/CheckoutSessionResource.php',
    'System/Console/Commands/ExpireCheckoutSessions.php',
    'database/migrations/2026_07_22_060006_checkout_engine.php',
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
$needles = [
    'CheckoutApiController',
    'checkout/customer',
    'checkout/delivery',
    'checkout/prepare',
    'checkout/approve',
    'checkout/cancel',
    'ExpireCheckoutSessions',
];
foreach ($needles as $needle) {
    $tests++;
    if (! str_contains($provider, $needle)) {
        fwrite(STDERR, "FAIL: provider missing {$needle}\n");
        exit(1);
    }
}

$runtime = file_get_contents($root . '/System/Services/CheckoutRuntime.php');
foreach (['getOrCreate', 'updateCustomer', 'selectDelivery', 'prepare', 'approve', 'cancel', 'expireDue', 'markCompleted'] as $method) {
    $tests++;
    if (! str_contains($runtime, 'function ' . $method . '(')) {
        fwrite(STDERR, "FAIL: checkout runtime missing {$method}\n");
        exit(1);
    }
}

echo "Checkout contract checks passed: {$tests}\n";
