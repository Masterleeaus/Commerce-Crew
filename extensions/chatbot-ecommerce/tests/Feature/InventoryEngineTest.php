<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class InventoryEngineTest extends TestCase
{
    public function test_inventory_engine_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        $required = [
            'System/Services/InventoryRuntime.php',
            'System/Support/InventoryAvailability.php',
            'System/Support/InventoryReservationState.php',
            'System/Models/InventoryLocation.php',
            'System/Models/InventoryLocationStock.php',
            'System/Models/InventoryReservation.php',
            'System/Models/InventoryAdjustment.php',
            'System/Http/Controllers/Api/InventoryApiController.php',
            'System/Console/Commands/ExpireInventoryReservations.php',
            'database/migrations/2026_07_22_060003_inventory_engine.php',
        ];

        foreach ($required as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_inventory_routes_and_idempotency_are_registered(): void
    {
        $root = dirname(__DIR__, 2);
        $provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
        $controller = file_get_contents($root . '/System/Http/Controllers/Api/InventoryApiController.php');

        self::assertStringContainsString('inventory/reservations', $provider);
        self::assertStringContainsString('inventory/{variant}/adjustments', $provider);
        self::assertStringContainsString('Idempotency-Key', $controller);
    }
}
