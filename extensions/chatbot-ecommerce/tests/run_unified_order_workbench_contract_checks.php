<?php

declare(strict_types=1);

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$required = [
    'System/Models/UnifiedCommerceOrder.php',
    'System/Models/UnifiedOrderSourceSnapshot.php',
    'System/Models/OrderSettlementEntry.php',
    'System/Models/OrderReconciliation.php',
    'System/Models/OrderException.php',
    'System/Models/OrderExceptionEvent.php',
    'System/Models/OrderWorkbenchAction.php',
    'System/Services/UnifiedOrderProjectorRuntime.php',
    'System/Services/OrderSettlementRuntime.php',
    'System/Services/OrderExceptionRuntime.php',
    'System/Services/UnifiedOrderWorkbenchRuntime.php',
    'System/Http/Controllers/Api/UnifiedOrderWorkbenchAdminApiController.php',
    'System/Http/Resources/Api/UnifiedCommerceOrderResource.php',
    'System/Http/Resources/Api/OrderExceptionResource.php',
    'database/migrations/2026_08_03_060021_unified_orders_settlements_exception_workbench.php',
];
foreach ($required as $file) {
    $assert(is_file(__DIR__ . '/../' . $file), "{$file} exists");
}

$projector = file_get_contents(__DIR__ . '/../System/Services/UnifiedOrderProjectorRuntime.php');
foreach (['projectNative', 'projectMarketplace', 'external_authoritative', 'UnifiedOrderSourceSnapshot', 'source_hash'] as $needle) {
    $assert(str_contains($projector, $needle), "projector contains {$needle}");
}

$settlements = file_get_contents(__DIR__ . '/../System/Services/OrderSettlementRuntime.php');
foreach (['importEntries', 'SettlementReconciliation::calculate', 'source_snapshot', 'source_scope', 'variance_amount', 'OrderReconciliation'] as $needle) {
    $assert(str_contains($settlements, $needle), "settlement runtime contains {$needle}");
}

$exceptions = file_get_contents(__DIR__ . '/../System/Services/OrderExceptionRuntime.php');
foreach (['invalid_address', 'payment_failure', 'fulfillment_delay', 'stock_conflict', 'disputed_return', 'settlement_variance'] as $needle) {
    $assert(str_contains($exceptions, $needle), "exception runtime contains {$needle}");
}

$workbench = file_get_contents(__DIR__ . '/../System/Services/UnifiedOrderWorkbenchRuntime.php');
foreach (['acknowledge', 'assign', 'resolve', 'prepareRefundProposal', 'prepareCustomerContact', 'linkCommunicationThread', 'request_hash', 'assertIdempotencyMatch'] as $needle) {
    $assert(str_contains($workbench, $needle), "workbench contains {$needle}");
}

$assert(str_contains($workbench, "status' => 'processing'"), 'low-risk action is reserved before side effects execute');
$assert(str_contains($workbench, 'Idempotency key was already used for a different order workbench request.'), 'idempotency collisions fail closed');

$migration = file_get_contents(__DIR__ . '/../database/migrations/2026_08_03_060021_unified_orders_settlements_exception_workbench.php');
$assert(str_contains($migration, "string('source_scope'"), 'settlement identity includes source scope');
$assert(str_contains($migration, "char('request_hash'"), 'workbench actions persist request hash');

$provider = file_get_contents(__DIR__ . '/../System/ChatbotEcommerceServiceProvider.php');
foreach (['UnifiedOrderWorkbenchAdminApiController', 'unified-orders', 'order-exceptions', 'settlements/import'] as $needle) {
    $assert(str_contains($provider, $needle), "provider contains {$needle}");
}

$manifest = json_decode(file_get_contents(__DIR__ . '/../extension.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($manifest['version'] ?? null) === '4.9.0', 'manifest version is 4.9.0');
foreach (['ext_chatbot_unified_orders', 'ext_chatbot_order_settlement_entries', 'ext_chatbot_order_exceptions'] as $table) {
    $assert(in_array($table, $manifest['database']['owned_tables'] ?? [], true), "manifest owns {$table}");
}
$assert(in_array('chatbot-ecommerce.manage-order-workbench', $manifest['permissions'] ?? [], true), 'manifest declares order workbench permission');

fwrite(STDOUT, "Unified order workbench contract checks passed: {$checks}\n");
