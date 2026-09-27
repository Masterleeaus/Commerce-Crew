<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/System/Support/MoneyMath.php';
require_once $root . '/System/Support/DiscountAllocator.php';

use App\Extensions\ChatbotEcommerce\System\Support\DiscountAllocator;
use App\Extensions\ChatbotEcommerce\System\Support\MoneyMath;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(MoneyMath::percentage(10_000, 2500) === 2500, '25% of 10000 should be 2500');
$assert(MoneyMath::percentage(1, 5000) === 1, 'percentage uses deterministic half-up rounding');
$assert(MoneyMath::clampDiscount(1200, 1000) === 1000, 'discount cannot exceed amount');
$assert(MoneyMath::inclusiveTax(11_000, 1000) === 1000, 'inclusive 10% tax portion is correct');
$assert(MoneyMath::exclusiveTax(10_000, 1000) === 1000, 'exclusive 10% tax is correct');

$allocation = DiscountAllocator::allocate(100, [
    ['key' => 'a', 'amount' => 300],
    ['key' => 'b', 'amount' => 200],
]);
$assert($allocation === ['a' => 60, 'b' => 40], 'fixed discount is allocated proportionally');

$allocation = DiscountAllocator::allocate(1, [
    ['key' => 'first', 'amount' => 1],
    ['key' => 'second', 'amount' => 1],
]);
$assert($allocation === ['first' => 1, 'second' => 0], 'allocation remainder is deterministic');

$allocation = DiscountAllocator::allocate(999, [
    ['key' => 'a', 'amount' => 20],
    ['key' => 'b', 'amount' => 30],
]);
$assert(array_sum($allocation) === 50, 'allocation cannot exceed eligible total');

fwrite(STDOUT, "Pricing primitive checks: {$assertions} passed\n");
