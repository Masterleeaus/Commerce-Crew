<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$required = [
    'database/migrations/2026_08_03_060012_conversational_commerce_slice1.php',
    'System/Models/ConversationContext.php',
    'System/Models/CommerceSpendLimit.php',
    'System/Models/CommerceOrder.php',
    'System/Models/CommerceOrderItem.php',
    'System/Models/CommerceOrderEvent.php',
    'System/Models/CommerceReturn.php',
    'System/Models/CommerceReturnItem.php',
    'System/Models/CommerceActionJournal.php',
    'System/Services/ConversationContextRuntime.php',
    'System/Services/FailureTranslationRuntime.php',
    'System/Services/BudgetLockRuntime.php',
    'System/Services/ConversationalCommerceRuntime.php',
    'System/Services/NativeOrderRuntime.php',
    'System/Services/CommerceCardRuntime.php',
    'System/Services/CommerceActionJournalRuntime.php',
    'System/Http/Controllers/Api/ConversationalCommerceApiController.php',
    'System/Http/Controllers/Api/NativeOrderApiController.php',
    'System/Http/Resources/Api/CommerceOrderResource.php',
    'System/Console/Commands/ExpireCommerceContexts.php',
    'openapi/conversational-commerce-v1.yaml',
];
foreach ($required as $file) {
    $assert(is_file($root . '/' . $file), "missing {$file}");
}

$provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
$assert(str_contains($provider, 'ConversationalCommerceApiController'), 'provider must register conversational commerce routes');
$assert(str_contains($provider, 'NativeOrderApiController'), 'provider must register order and return routes');
$assert(str_contains($provider, 'commerce/context'), 'context endpoint must be registered');
$assert(str_contains($provider, 'commerce/tools/execute'), 'tool execution endpoint must be registered');
$assert(str_contains($provider, 'commerce/actions/{actionUuid}/execute'), 'approved actions need a dedicated user action endpoint');
$assert(str_contains($provider, 'ExpireCommerceContexts'), 'context expiration command must be registered');
$assert(str_contains($provider, 'orders/{order:uuid}/returns'), 'return endpoint must be registered');

$toolService = file_get_contents($root . '/System/Services/EcommerceToolService.php');
$assert(str_contains($toolService, 'ConversationalCommerceRuntime'), 'legacy tool service must bridge to native conversational runtime');
$assert(str_contains($toolService, 'native_'), 'native AI tool declarations must be exposed');
$runtimeSource = file_get_contents($root . '/System/Services/ConversationalCommerceRuntime.php');
$definitionsSection = substr($runtimeSource, strpos($runtimeSource, 'public function toolDefinitions'), strpos($runtimeSource, 'public function execute') - strpos($runtimeSource, 'public function toolDefinitions'));
$assert(! str_contains($definitionsSection, "definition('native_execute_action'"), 'the AI model must not receive the execute-action tool');
$paymentRuntime = file_get_contents($root . '/System/Services/PaymentRuntime.php');
$assert(str_contains($paymentRuntime, 'createFromCheckout'), 'captured checkout payments must materialise native orders');

$manifest = json_decode((string) file_get_contents($root . '/extension.manifest.json'), true);
$assert(is_array($manifest), 'sidecar manifest must remain valid JSON');
$manifestText = json_encode($manifest);
$assert(str_contains((string) $manifestText, 'conversational_context'), 'manifest must declare conversational context capability');
$assert(str_contains((string) $manifestText, 'native_orders'), 'manifest must declare native orders capability');
$assert(str_contains((string) $manifestText, 'budget_locks'), 'manifest must declare budget locks capability');
$assert(str_contains((string) $manifestText, 'chatbot-ecommerce:contexts-expire'), 'manifest must declare context cleanup schedule');

$readme = file_get_contents($root . '/README.md');
$assert(str_contains($readme, 'Customer Commerce Assistant'), 'README must describe customer mode');
$assert(str_contains($readme, 'Seller Commerce Steward'), 'README must preserve two-mode architecture');
$assert(str_contains($readme, 'Inform'), 'README must document approval levels');
$assert(str_contains($readme, 'Rollback'), 'README must document action rollback safety');

echo "Slice 1 contract checks passed: {$checks}\n";
