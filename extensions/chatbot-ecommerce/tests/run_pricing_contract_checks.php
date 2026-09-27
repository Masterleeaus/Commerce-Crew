<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'migration' => $root . '/database/migrations/2026_07_22_060005_pricing_discount_engine.php',
    'pricing_rule_model' => $root . '/System/Models/PricingRule.php',
    'pricing_snapshot_model' => $root . '/System/Models/PricingSnapshot.php',
    'coupon_usage_model' => $root . '/System/Models/CouponUsage.php',
    'coupon_runtime' => $root . '/System/Services/CouponRuntime.php',
    'pricing_admin_controller' => $root . '/System/Http/Controllers/Api/PricingAdminApiController.php',
];

foreach ($checks as $name => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$name}: {$path}\n");
        exit(1);
    }
}

$pricing = file_get_contents($root . '/System/Services/PricingRuntime.php');
$cart = file_get_contents($root . '/System/Services/CartRuntime.php');
$provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
$migration = file_get_contents($checks['migration']);

$requiredPricingSignals = [
    'price_override',
    'percentage_discount',
    'fixed_discount',
    'free_shipping',
    'snapshot_hash',
    'tax_rate_bps',
    'DiscountAllocator',
];
foreach ($requiredPricingSignals as $signal) {
    if (! str_contains($pricing, $signal)) {
        fwrite(STDERR, "FAIL: PricingRuntime missing {$signal}\n");
        exit(1);
    }
}

foreach (["pricing/rules", "pricing/coupons", "pricing/snapshots"] as $route) {
    if (! str_contains($provider, $route)) {
        fwrite(STDERR, "FAIL: service provider missing route {$route}\n");
        exit(1);
    }
}

foreach (['ext_chatbot_pricing_rules', 'ext_chatbot_pricing_snapshots', 'ext_chatbot_coupon_usages'] as $table) {
    if (! str_contains($migration, $table)) {
        fwrite(STDERR, "FAIL: migration missing {$table}\n");
        exit(1);
    }
}

if (! str_contains($cart, 'persistPricingSnapshot')) {
    fwrite(STDERR, "FAIL: cart runtime does not persist immutable pricing snapshots\n");
    exit(1);
}

fwrite(STDOUT, "Pricing contract checks: 15 passed\n");
