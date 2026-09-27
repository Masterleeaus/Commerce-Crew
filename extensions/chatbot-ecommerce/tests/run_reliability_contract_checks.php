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
    'System/Support/RetryBackoff.php',
    'System/Support/CircuitBreakerDecision.php',
    'System/Support/LifecycleStatus.php',
    'System/Services/CommerceScheduleRegistrar.php',
    'System/Services/CommerceLifecycleRuntime.php',
    'System/Services/ProviderCircuitBreakerRuntime.php',
    'System/Services/CommerceTenantContext.php',
    'System/Queue/Middleware/RestoreCommerceTenantContext.php',
    'System/Jobs/ProcessPaymentWebhook.php',
    'System/Http/Middleware/EnsureCommerceExtensionEnabled.php',
    'System/Models/CommerceLifecycleState.php',
    'System/Models/ProviderCircuitBreaker.php',
    'database/migrations/2026_08_03_060020_scheduler_queue_webhook_lifecycle_reliability.php',
];
foreach ($required as $file) {
    $assert(is_file(__DIR__ . '/../' . $file), "{$file} exists");
}

$provider = file_get_contents(__DIR__ . '/../System/ChatbotEcommerceServiceProvider.php');
foreach (['CommerceScheduleRegistrar', 'EnsureCommerceExtensionEnabled', 'ProcessPaymentWebhook', 'registerSchedules', 'CommerceLifecycleRuntime'] as $needle) {
    $assert(str_contains($provider, $needle), "provider contains {$needle}");
}

$scheduler = file_get_contents(__DIR__ . '/../System/Services/CommerceScheduleRegistrar.php');
foreach (['withoutOverlapping', 'onOneServer', 'chatbot-ecommerce:expire-reservations', 'chatbot-ecommerce:marketplace-scan-inventory'] as $needle) {
    $assert(str_contains($scheduler, $needle), "scheduler contains {$needle}");
}

$webhookController = file_get_contents(__DIR__ . '/../System/Http/Controllers/Api/PaymentWebhookController.php');
$assert(str_contains($webhookController, 'receiveWebhook'), 'webhook controller only verifies and persists');
$assert(str_contains($webhookController, 'ProcessPaymentWebhook::dispatch'), 'webhook controller queues processing');
$assert(str_contains($webhookController, '202'), 'webhook controller acknowledges asynchronously');

$paymentRuntime = file_get_contents(__DIR__ . '/../System/Services/PaymentRuntime.php');
foreach (['receiveWebhook', 'processReceivedWebhook', 'dead_lettered', 'next_attempt_at'] as $needle) {
    $assert(str_contains($paymentRuntime, $needle), "payment runtime contains {$needle}");
}

foreach (['ExecuteMarketplaceWrite.php', 'ImportMarketplaceOrders.php', 'SearchMarketplaceListings.php', 'ScanMarketplaceInventoryConflicts.php', 'ProcessPaymentWebhook.php'] as $job) {
    $source = file_get_contents(__DIR__ . '/../System/Jobs/' . $job);
    $assert(str_contains($source, 'RestoreCommerceTenantContext'), "{$job} restores tenant context");
    $assert(str_contains($source, 'backoff'), "{$job} declares retry backoff");
}

$lifecycle = file_get_contents(__DIR__ . '/../System/Services/CommerceLifecycleRuntime.php');
foreach (['enable', 'disable', 'beginUninstall', 'completeUninstall', 'preserve_data'] as $needle) {
    $assert(str_contains($lifecycle, $needle), "lifecycle runtime contains {$needle}");
}

$manifest = json_decode(file_get_contents(__DIR__ . '/../extension.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(version_compare((string) ($manifest['version'] ?? '0.0.0'), '4.8.0', '>='), 'manifest version preserves the v4.8.0 reliability floor');
$assert(in_array('chatbot-ecommerce-payment-webhooks', $manifest['queues']['queue_names'] ?? [], true), 'manifest declares payment webhook queue');
$assert(in_array('ext_chatbot_ecommerce_lifecycle_states', $manifest['database']['owned_tables'] ?? [], true), 'manifest owns lifecycle state table');
$assert(in_array('ext_chatbot_provider_circuit_breakers', $manifest['database']['owned_tables'] ?? [], true), 'manifest owns circuit breaker table');

fwrite(STDOUT, "Reliability contract checks passed: {$checks}\n");
