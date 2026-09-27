<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class RentalHireReceivablesTest extends TestCase
{
    public function test_rental_hire_receivables_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Services/RentalHireRuntime.php',
            'System/Support/RentalBillingSchedule.php',
            'System/Support/RentalAllocationCalculator.php',
            'System/Models/RentalAccount.php',
            'System/Models/RentalAgreement.php',
            'System/Models/RentalCharge.php',
            'System/Models/RentalPayment.php',
            'System/Models/RentalReceipt.php',
            'System/Http/Controllers/Api/RentalHireApiController.php',
            'System/Http/Controllers/Api/RentalHireAdminApiController.php',
            'database/migrations/2026_07_22_060009_rental_hire_receivables.php',
        ] as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_service_provider_exposes_customer_and_admin_routes(): void
    {
        $provider = file_get_contents(dirname(__DIR__, 2) . '/System/ChatbotEcommerceServiceProvider.php');
        self::assertStringContainsString('rental-hire/accounts', $provider);
        self::assertStringContainsString('RentalHireApiController', $provider);
        self::assertStringContainsString('RentalHireAdminApiController', $provider);
    }
}
