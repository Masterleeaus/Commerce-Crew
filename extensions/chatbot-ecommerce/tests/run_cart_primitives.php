<?php

declare(strict_types=1);

require dirname(__DIR__) . '/System/Support/CartStatus.php';
require dirname(__DIR__) . '/System/Support/CartLineQuantity.php';
require dirname(__DIR__) . '/System/Support/CartLineKey.php';

use App\Extensions\ChatbotEcommerce\System\Support\CartLineKey;
use App\Extensions\ChatbotEcommerce\System\Support\CartLineQuantity;
use App\Extensions\ChatbotEcommerce\System\Support\CartStatus;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$assert(CartStatus::canTransition(CartStatus::ACTIVE, CartStatus::ABANDONED), 'active carts may be abandoned');
$assert(CartStatus::canTransition(CartStatus::ABANDONED, CartStatus::ACTIVE), 'abandoned carts may be recovered');
$assert(CartStatus::canTransition(CartStatus::ACTIVE, CartStatus::CONVERTED), 'active carts may convert');
$assert(! CartStatus::canTransition(CartStatus::CONVERTED, CartStatus::ACTIVE), 'converted carts are terminal');
$assert(CartLineQuantity::normalise(4) === 4, 'valid quantities are retained');
$assert(CartLineQuantity::normalise(0) === 0, 'zero removes a line');
$assert(CartLineQuantity::normalise(1000) === 999, 'quantities are bounded');
$assert(CartLineKey::make(12, ['size' => 'M', 'colour' => 'blue']) === CartLineKey::make(12, ['colour' => 'blue', 'size' => 'M']), 'customisation order does not change the line key');

fwrite(STDOUT, "Cart primitives: {$assertions} passed\n");
