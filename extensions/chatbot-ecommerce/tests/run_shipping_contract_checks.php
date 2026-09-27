<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'System/Contracts/ShippingProvider.php',
    'System/Providers/InternalShippingProvider.php',
    'System/Models/ShippingZone.php',
    'System/Models/ShippingMethod.php',
    'System/Models/ShippingQuote.php',
    'System/Models/Fulfillment.php',
    'System/Models/FulfillmentItem.php',
    'System/Services/ShippingRuntime.php',
    'System/Services/FulfillmentRuntime.php',
    'System/Http/Controllers/Api/ShippingApiController.php',
    'System/Http/Controllers/Api/ShippingAdminApiController.php',
    'System/Http/Controllers/Api/FulfillmentApiController.php',
    'database/migrations/2026_07_22_060007_shipping_fulfillment_engine.php',
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
foreach (['ShippingProvider', 'InternalShippingProvider', 'ShippingApiController', 'ShippingAdminApiController', 'FulfillmentApiController', 'shipping-options', 'shipping/zones', 'fulfillments'] as $needle) {
    $tests++;
    if (! str_contains($provider, $needle)) {
        fwrite(STDERR, "FAIL: service provider missing {$needle}\n");
        exit(1);
    }
}

$checkout = file_get_contents($root . '/System/Services/CheckoutRuntime.php');
foreach (['ShippingRuntime', 'quoteForCheckout', 'assertQuoteStillValid'] as $needle) {
    $tests++;
    if (! str_contains($checkout, $needle)) {
        fwrite(STDERR, "FAIL: checkout runtime missing {$needle}\n");
        exit(1);
    }
}

$shipping = file_get_contents($root . '/System/Services/ShippingRuntime.php');
foreach (['quote', 'quoteForCheckout', 'assertQuoteStillValid', 'expireQuotes'] as $method) {
    $tests++;
    if (! str_contains($shipping, 'function ' . $method . '(')) {
        fwrite(STDERR, "FAIL: shipping runtime missing {$method}\n");
        exit(1);
    }
}

$fulfillment = file_get_contents($root . '/System/Services/FulfillmentRuntime.php');
foreach (['create', 'markProcessing', 'markShipped', 'markDelivered', 'cancel', 'summaryForCheckout'] as $method) {
    $tests++;
    if (! str_contains($fulfillment, 'function ' . $method . '(')) {
        fwrite(STDERR, "FAIL: fulfillment runtime missing {$method}\n");
        exit(1);
    }
}

$health = file_get_contents($root . '/System/Services/ExtensionHealthService.php');
foreach (['shipping_engine_tables', 'fulfillment_engine_tables', 'expired_shipping_quotes'] as $needle) {
    $tests++;
    if (! str_contains($health, $needle)) {
        fwrite(STDERR, "FAIL: health service missing {$needle}\n");
        exit(1);
    }
}

echo "Shipping and fulfillment contract checks passed: {$tests}\n";
