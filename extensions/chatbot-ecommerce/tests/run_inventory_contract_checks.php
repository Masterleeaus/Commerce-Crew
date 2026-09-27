<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
$controller = file_get_contents($root . '/System/Http/Controllers/Api/InventoryApiController.php');
$runtime = file_get_contents($root . '/System/Services/InventoryRuntime.php');

$checks = [
    str_contains($provider, "inventory/locations"),
    str_contains($controller, 'function locations('),
    str_contains($controller, 'function storeLocation('),
    str_contains($controller, 'function updateLocation('),
    str_contains($runtime, 'Idempotency-Key'),
];

foreach ($checks as $index => $check) {
    if (! $check) {
        fwrite(STDERR, 'Inventory contract check failed at index ' . $index . PHP_EOL);
        exit(1);
    }
}

echo count($checks) . " inventory contract checks passed\n";
