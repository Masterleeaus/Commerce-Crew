<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/System/Support/RentalBillingSchedule.php';
require $root . '/System/Support/RentalAllocationCalculator.php';

use App\Extensions\ChatbotEcommerce\System\Support\RentalAllocationCalculator;
use App\Extensions\ChatbotEcommerce\System\Support\RentalBillingSchedule;

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$weekly = RentalBillingSchedule::periods('2026-07-01', '2026-07-31', 'weekly', 1, 0, null, 10);
$assert(count($weekly) === 5, 'weekly schedule creates five July periods');
$assert($weekly[0]['period_start'] === '2026-07-01', 'weekly first period starts on agreement date');
$assert($weekly[0]['period_end'] === '2026-07-07', 'weekly period ends after seven days');
$assert($weekly[1]['due_date'] === '2026-07-08', 'weekly due date follows each period start');

$fortnightly = RentalBillingSchedule::periods('2026-07-01', '2026-08-01', 'fortnightly', 1, 2, null, 10);
$assert(count($fortnightly) === 3, 'fortnightly schedule creates bounded periods');
$assert($fortnightly[0]['period_end'] === '2026-07-14', 'fortnightly period has fourteen days');
$assert($fortnightly[0]['due_date'] === '2026-07-03', 'due offset is applied');

$monthly = RentalBillingSchedule::periods('2026-01-31', '2026-04-30', 'monthly', 1, 0, null, 10);
$assert($monthly[0]['period_end'] === '2026-02-27', 'monthly schedule handles short February deterministically');
$assert($monthly[1]['period_start'] === '2026-02-28', 'monthly schedule continues without a gap');

$oneTime = RentalBillingSchedule::periods('2026-07-01', '2026-12-31', 'one_time', 1, 0, '2026-07-10', 10);
$assert(count($oneTime) === 1, 'one-time hire creates one charge period');
$assert($oneTime[0]['period_end'] === '2026-07-10', 'one-time hire uses agreement end date');

$allocation = RentalAllocationCalculator::allocate(90000, [
    ['uuid' => 'charge-b', 'balance_due' => 60000, 'due_date' => '2026-07-15'],
    ['uuid' => 'charge-a', 'balance_due' => 40000, 'due_date' => '2026-07-01'],
]);
$assert($allocation['allocations'][0]['charge_uuid'] === 'charge-a', 'oldest charge is allocated first');
$assert($allocation['allocations'][0]['amount'] === 40000, 'oldest charge is fully paid');
$assert($allocation['allocations'][1]['amount'] === 50000, 'remaining payment partially pays next charge');
$assert($allocation['unallocated'] === 0, 'fully allocated payment has no credit remainder');

$overpayment = RentalAllocationCalculator::allocate(120000, [
    ['uuid' => 'charge-a', 'balance_due' => 40000, 'due_date' => '2026-07-01'],
]);
$assert($overpayment['unallocated'] === 80000, 'overpayment remains as account credit');
$assert($overpayment['allocated_total'] === 40000, 'allocation never exceeds charge balance');

$partial = RentalAllocationCalculator::allocate(10000, [
    ['uuid' => 'charge-a', 'balance_due' => 40000, 'due_date' => '2026-07-01'],
]);
$assert($partial['allocations'][0]['amount'] === 10000, 'partial payment allocation is supported');

$empty = RentalAllocationCalculator::allocate(25000, []);
$assert($empty['unallocated'] === 25000, 'payment without charges remains account credit');

echo "Rental/hire primitive checks passed: {$tests}\n";
