<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/System/Support/InventoryAvailability.php';
require $root . '/System/Support/InventoryReservationState.php';

use App\Extensions\ChatbotEcommerce\System\Support\InventoryAvailability;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryReservationState;

$assertions = [
    InventoryAvailability::calculate(10, 2, 1, 1, 1) === 5,
    InventoryAvailability::calculate(2, 2, 1, 0, 0) === 0,
    InventoryReservationState::canTransition('active', 'committed'),
    InventoryReservationState::canTransition('active', 'released'),
    InventoryReservationState::canTransition('active', 'expired'),
    ! InventoryReservationState::canTransition('committed', 'released'),
    ! InventoryReservationState::canTransition('released', 'committed'),
    ! InventoryReservationState::canTransition('expired', 'committed'),
];

foreach ($assertions as $index => $assertion) {
    if (! $assertion) {
        fwrite(STDERR, 'Assertion failed at index ' . $index . PHP_EOL);
        exit(1);
    }
}

echo count($assertions) . " inventory primitive assertions passed\n";
