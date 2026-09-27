<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'System/Models/ChatbotCartLine.php',
    'System/Models/CartOperation.php',
    'System/Http/Requests/PutCartLineRequest.php',
    'System/Http/Requests/MergeCartRequest.php',
    'System/Console/Commands/ExpireCarts.php',
    'database/migrations/2026_07_22_060004_native_cart_engine.php',
];

foreach ($required as $file) {
    if (! is_file($root . '/' . $file)) {
        fwrite(STDERR, "Missing required cart engine file: {$file}\n");
        exit(1);
    }
}

$runtime = file_get_contents($root . '/System/Services/CartRuntime.php');
foreach (['addLine(', 'setLine(', 'removeLine(', 'clear(', 'recalculate(', 'merge(', 'abandon(', 'recover(', 'expireDue('] as $method) {
    if (! str_contains($runtime, $method)) {
        fwrite(STDERR, "CartRuntime is missing {$method}\n");
        exit(1);
    }
}

$provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
foreach (['cart/lines', 'cart/recalculate', 'cart/merge', 'cart/abandon', 'cart/recover'] as $route) {
    if (! str_contains($provider, $route)) {
        fwrite(STDERR, "Route registration is missing {$route}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Cart contract checks: 15 passed\n");
