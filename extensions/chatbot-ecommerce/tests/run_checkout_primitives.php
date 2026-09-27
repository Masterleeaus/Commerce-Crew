<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/System/Support/CheckoutStatus.php';
require $root . '/System/Support/CheckoutAddress.php';

use App\Extensions\ChatbotEcommerce\System\Support\CheckoutAddress;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutStatus;

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(CheckoutStatus::canTransition('draft', 'customer_details'), 'draft can accept customer details');
$assert(CheckoutStatus::canTransition('customer_details', 'delivery_selected'), 'customer details can select delivery');
$assert(CheckoutStatus::canTransition('delivery_selected', 'ready'), 'delivery can become ready');
$assert(CheckoutStatus::canTransition('ready', 'payment_pending'), 'ready can become payment pending');
$assert(! CheckoutStatus::canTransition('completed', 'draft'), 'completed is terminal');
$assert(CheckoutStatus::isTerminal('expired'), 'expired is terminal');

$address = CheckoutAddress::normalise([
    'name' => '  Jane Doe ',
    'line1' => ' 1 Main St ',
    'city' => ' Melbourne ',
    'region' => ' VIC ',
    'postcode' => ' 3000 ',
    'country' => 'au',
]);
$assert($address['name'] === 'Jane Doe', 'name is trimmed');
$assert($address['country'] === 'AU', 'country is upper-cased');
$assert(CheckoutAddress::isComplete($address), 'normalised address is complete');
$assert(! CheckoutAddress::isComplete(['line1' => '1 Main St']), 'partial address is incomplete');

echo "Checkout primitive checks passed: {$tests}\n";
