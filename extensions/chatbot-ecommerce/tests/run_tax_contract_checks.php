<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'System/Contracts/TaxProvider.php',
    'System/Providers/InternalTaxProvider.php',
    'System/Models/TaxZone.php',
    'System/Models/TaxRate.php',
    'System/Models/TaxExemption.php',
    'System/Services/TaxRuntime.php',
    'System/Support/TaxZoneMatcher.php',
    'System/Support/TaxCalculator.php',
    'System/Http/Controllers/Api/TaxAdminApiController.php',
    'database/migrations/2026_07_22_060008_tax_engine.php',
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
foreach (['TaxProvider', 'InternalTaxProvider', 'TaxAdminApiController', 'tax/zones', 'tax/rates', 'tax/exemptions'] as $needle) {
    $tests++;
    if (! str_contains($provider, $needle)) {
        fwrite(STDERR, "FAIL: service provider missing {$needle}\n");
        exit(1);
    }
}

$pricing = file_get_contents($root . '/System/Services/PricingRuntime.php');
foreach (['TaxRuntime', 'tax_breakdown', 'tax_context_hash', 'CALCULATION_VERSION'] as $needle) {
    $tests++;
    if (! str_contains($pricing, $needle)) {
        fwrite(STDERR, "FAIL: pricing runtime missing {$needle}\n");
        exit(1);
    }
}

$tax = file_get_contents($root . '/System/Services/TaxRuntime.php');
foreach (['calculate', 'resolveContext', 'isExempt', 'contextHash'] as $method) {
    $tests++;
    if (! str_contains($tax, 'function ' . $method . '(')) {
        fwrite(STDERR, "FAIL: tax runtime missing {$method}\n");
        exit(1);
    }
}

$checkout = file_get_contents($root . '/System/Services/CheckoutRuntime.php');
foreach (['tax_address', 'tax_exemption_key', 'tax_context_hash'] as $needle) {
    $tests++;
    if (! str_contains($checkout, $needle)) {
        fwrite(STDERR, "FAIL: checkout runtime missing {$needle}\n");
        exit(1);
    }
}

$health = file_get_contents($root . '/System/Services/ExtensionHealthService.php');
foreach (['tax_engine_tables', 'active_tax_rates_without_zone', 'ready_checkouts_without_tax_context'] as $needle) {
    $tests++;
    if (! str_contains($health, $needle)) {
        fwrite(STDERR, "FAIL: health service missing {$needle}\n");
        exit(1);
    }
}

echo "Tax contract checks passed: {$tests}\n";
