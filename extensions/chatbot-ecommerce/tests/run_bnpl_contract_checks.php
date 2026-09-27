<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'System/Services/BnplRuntime.php',
    'System/Models/BnplProviderProfile.php',
    'System/Models/BnplOffer.php',
    'System/Support/BnplStatus.php',
    'System/Support/BnplEligibility.php',
    'System/Support/BnplInstallmentSchedule.php',
    'System/Support/BnplStateToken.php',
    'System/Support/ShoppingMode.php',
    'System/Http/Controllers/Api/BnplApiController.php',
    'System/Http/Controllers/Api/BnplAdminApiController.php',
    'System/Http/Resources/Api/BnplOfferResource.php',
    'database/migrations/2026_08_03_060011_bnpl_engine.php',
    'extension.manifest.json',
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
foreach (['BnplApiController', 'BnplAdminApiController', 'bnpl/offers', 'bnpl/providers'] as $needle) {
    $tests++;
    if (! str_contains($provider, $needle)) {
        fwrite(STDERR, "FAIL: service provider missing {$needle}\n");
        exit(1);
    }
}

$runtime = file_get_contents($root . '/System/Services/BnplRuntime.php');
foreach (['offersForCheckout', 'selectForCheckout', 'offersForRentalPayment', 'selectForRentalPayment', 'expireDue'] as $method) {
    $tests++;
    if (! str_contains($runtime, 'function ' . $method . '(')) {
        fwrite(STDERR, "FAIL: BNPL runtime missing {$method}\n");
        exit(1);
    }
}

$config = file_get_contents($root . '/config/platform-quality.php');
foreach (['bnpl_payments', "'bnpl'", 'marketplace_assisted', 'require_licensed_provider', 'rent_enabled'] as $needle) {
    $tests++;
    if (! str_contains($config, $needle)) {
        fwrite(STDERR, "FAIL: BNPL configuration missing {$needle}\n");
        exit(1);
    }
}

$paymentRuntime = file_get_contents($root . '/System/Services/PaymentRuntime.php');
foreach (['BnplOffer', 'synchroniseBnplOffer'] as $needle) {
    $tests++;
    if (! str_contains($paymentRuntime, $needle)) {
        fwrite(STDERR, "FAIL: payment runtime missing {$needle}\n");
        exit(1);
    }
}

echo "BNPL contract checks passed: {$tests}\n";
