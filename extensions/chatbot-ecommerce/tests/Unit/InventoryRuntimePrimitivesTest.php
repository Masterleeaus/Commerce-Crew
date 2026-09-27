<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryAvailability;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryReservationState;

require_once dirname(__DIR__, 2) . '/System/Support/InventoryAvailability.php';
require_once dirname(__DIR__, 2) . '/System/Support/InventoryReservationState.php';

final class InventoryRuntimePrimitivesTest extends TestCase
{
    public function test_available_stock_excludes_reserved_committed_damaged_and_safety_stock(): void
    {
        self::assertSame(5, InventoryAvailability::calculate(10, 2, 1, 1, 1));
    }

    public function test_available_stock_never_becomes_negative(): void
    {
        self::assertSame(0, InventoryAvailability::calculate(2, 2, 1, 0, 0));
    }

    public function test_active_reservation_can_reach_each_terminal_state(): void
    {
        self::assertTrue(InventoryReservationState::canTransition('active', 'committed'));
        self::assertTrue(InventoryReservationState::canTransition('active', 'released'));
        self::assertTrue(InventoryReservationState::canTransition('active', 'expired'));
    }

    public function test_terminal_reservation_cannot_transition_again(): void
    {
        self::assertFalse(InventoryReservationState::canTransition('committed', 'released'));
        self::assertFalse(InventoryReservationState::canTransition('released', 'committed'));
        self::assertFalse(InventoryReservationState::canTransition('expired', 'committed'));
    }
}
