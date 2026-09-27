<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'System/Models/RentalAccount.php',
    'System/Models/RentalAgreement.php',
    'System/Models/RentalAgreementRate.php',
    'System/Models/RentalCharge.php',
    'System/Models/RentalPayment.php',
    'System/Models/RentalPaymentAllocation.php',
    'System/Models/RentalAdjustment.php',
    'System/Models/RentalReceipt.php',
    'System/Models/RentalLedgerEntry.php',
    'System/Services/RentalHireRuntime.php',
    'System/Support/RentalBillingSchedule.php',
    'System/Support/RentalAllocationCalculator.php',
    'System/Support/RentalHireStatus.php',
    'System/Http/Controllers/Api/RentalHireApiController.php',
    'System/Http/Controllers/Api/RentalHireAdminApiController.php',
    'System/Http/Resources/Api/RentalAccountResource.php',
    'System/Http/Resources/Api/RentalAgreementResource.php',
    'System/Http/Resources/Api/RentalPaymentResource.php',
    'System/Console/Commands/GenerateRentalHireCharges.php',
    'System/Console/Commands/ExpireRentalHirePaymentRequests.php',
    'database/migrations/2026_07_22_060009_rental_hire_receivables.php',
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
foreach (['RentalHireApiController', 'RentalHireAdminApiController', 'rental-hire/accounts', 'GenerateRentalHireCharges', 'ExpireRentalHirePaymentRequests'] as $needle) {
    $tests++;
    if (! str_contains($provider, $needle)) {
        fwrite(STDERR, "FAIL: service provider missing {$needle}\n");
        exit(1);
    }
}

$runtime = file_get_contents($root . '/System/Services/RentalHireRuntime.php');
foreach (['createAccount', 'createAgreement', 'generateCharges', 'createPaymentRequest', 'confirmPayment', 'allocatePayment', 'reversePayment', 'createAdjustment', 'accountSummary', 'ledger', 'receipt'] as $method) {
    $tests++;
    if (! str_contains($runtime, 'function ' . $method . '(')) {
        fwrite(STDERR, "FAIL: rental runtime missing {$method}\n");
        exit(1);
    }
}

$health = file_get_contents($root . '/System/Services/ExtensionHealthService.php');
foreach (['rental_hire_tables', 'overdue_rental_charges', 'received_rental_payments_unallocated', 'rental_receipts_without_hash'] as $needle) {
    $tests++;
    if (! str_contains($health, $needle)) {
        fwrite(STDERR, "FAIL: health service missing {$needle}\n");
        exit(1);
    }
}

$config = file_get_contents($root . '/config/platform-quality.php');
foreach (['rental_hire_receivables', "'rental_hire'", 'allowed_payment_methods', 'charge_generation_horizon_days'] as $needle) {
    $tests++;
    if (! str_contains($config, $needle)) {
        fwrite(STDERR, "FAIL: configuration missing {$needle}\n");
        exit(1);
    }
}

echo "Rental/hire contract checks passed: {$tests}\n";
