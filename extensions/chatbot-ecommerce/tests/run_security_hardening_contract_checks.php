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

$files = [
    'System/Models/CommerceCredential.php',
    'System/Services/CommerceCredentialRuntime.php',
    'System/Services/CommerceCredentialBridge.php',
    'System/Services/CommerceTenantRuntime.php',
    'System/Support/CommerceSessionAuthority.php',
    'System/Support/CredentialRedactor.php',
    'System/Support/CommercePermissionMap.php',
    'System/Http/Middleware/RequireCommerceSessionAuthority.php',
    'System/Http/Middleware/EnsureCommerceAdminAccess.php',
    'System/Http/Controllers/Api/CommerceCredentialAdminApiController.php',
    'System/Http/Controllers/Api/CommerceSessionAuthorityApiController.php',
    'database/migrations/2026_08_03_060019_credential_authorization_tenant_hardening.php',
];
foreach ($files as $file) {
    $assert(is_file(__DIR__ . '/../' . $file), "{$file} exists");
}

$model = file_get_contents(__DIR__ . '/../System/Models/CommerceCredential.php');
foreach (["'credentials' => 'encrypted:array'", "'configuration' => 'encrypted:array'", "protected \$hidden"] as $needle) {
    $assert(str_contains($model, $needle), "credential model contains {$needle}");
}

$provider = file_get_contents(__DIR__ . '/../System/ChatbotEcommerceServiceProvider.php');
foreach (['CommerceCredentialBridge', 'EnsureCommerceAdminAccess', 'RequireCommerceSessionAuthority', 'session-authorities', 'commerce/credentials', 'Gate::policy', 'ExtensionAccessPolicy'] as $needle) {
    $assert(str_contains($provider, $needle), "provider contains {$needle}");
}

$runtime = file_get_contents(__DIR__ . '/../System/Services/CommerceCredentialRuntime.php');
foreach (['rotate', 'revoke', 'testConnection', 'credential_reference', 'CredentialRedactor'] as $needle) {
    $assert(str_contains($runtime, $needle), "credential runtime contains {$needle}");
}

$bridge = file_get_contents(__DIR__ . '/../System/Services/CommerceCredentialBridge.php');
foreach (['shopify_access_token', 'woocommerce_consumer_key', 'woocommerce_consumer_secret', 'saving', 'saved', 'setAttribute'] as $needle) {
    $assert(str_contains($bridge, $needle), "credential bridge contains {$needle}");
}

$sessionMiddleware = file_get_contents(__DIR__ . '/../System/Http/Middleware/RequireCommerceSessionAuthority.php');
foreach (['X-Commerce-Session-Token', 'chatbot_id', 'session_id', 'customer_identity_id', 'requiredCapability'] as $needle) {
    $assert(str_contains($sessionMiddleware, $needle), "session middleware contains {$needle}");
}

$credentialConsumers = [
    'System/Http/Controllers/Api/ChatbotEcommerceApiController.php',
    'System/Services/EcommerceToolService.php',
];
foreach ($credentialConsumers as $file) {
    $source = file_get_contents(__DIR__ . '/../' . $file);
    $assert(str_contains($source, 'CommerceCredentialRuntime'), "{$file} uses credential runtime");
    $assert(! preg_match('/\$chatbot->(?:shopify_access_token|woocommerce_consumer_key|woocommerce_consumer_secret)/', $source), "{$file} does not read plaintext chatbot credentials");
}

$view = file_get_contents(__DIR__ . '/../resources/views/particles/chatbot-config.blade.php');
$assert(! str_contains($view, 'x-model="activeChatbot.shopify_access_token"'), 'view does not bind Shopify token from API resource');
$assert(! str_contains($view, 'x-model="activeChatbot.woocommerce_consumer_secret"'), 'view does not bind Woo secret from API resource');
$assert(str_contains($view, 'type="password"'), 'credential inputs use password type');

$migration = file_get_contents(__DIR__ . '/../database/migrations/2026_08_03_060019_credential_authorization_tenant_hardening.php');
foreach (['ext_chatbot_ecommerce_credentials', 'Crypt::encryptString', 'shopify_access_token' => null, 'woocommerce_consumer_secret' => null] as $needle) {
    if (is_array($needle)) { continue; }
}
$assert(str_contains($migration, 'ext_chatbot_ecommerce_credentials'), 'migration creates credential table');
$assert(str_contains($migration, 'Crypt::encryptString'), 'migration encrypts legacy credentials');
$assert(str_contains($migration, "'shopify_access_token' => null"), 'migration clears legacy Shopify plaintext');
$assert(str_contains($migration, "'woocommerce_consumer_secret' => null"), 'migration clears legacy Woo plaintext');


$bridgeSource = file_get_contents(__DIR__ . '/../System/Services/CommerceCredentialBridge.php');
$assert(str_contains($bridgeSource, 'makeHidden(self::LEGACY_FIELDS)'), 'legacy credential fields are omitted from serialization');
$assert(str_contains($bridgeSource, "setRelation('__commerce_pending_credentials'"), 'legacy credentials use request-local model state rather than a static secret buffer');

$cartRuntime = file_get_contents(__DIR__ . '/../System/Services/CartRuntime.php');
$assert(str_contains($cartRuntime, 'assertVariantForCart'), 'cart runtime rejects variants from another storefront');
$mergeRequest = file_get_contents(__DIR__ . '/../System/Http/Requests/MergeCartRequest.php');
$assert(str_contains($mergeRequest, 'source_recovery_token'), 'cart merge requires source-cart authority');

foreach ([
    'System/Http/Controllers/Api/PricingAdminApiController.php',
    'System/Http/Controllers/Api/TaxAdminApiController.php',
    'System/Http/Controllers/Api/ShippingAdminApiController.php',
    'System/Http/Controllers/Api/InventoryApiController.php',
    'System/Http/Controllers/Api/PaymentAdminApiController.php',
    'System/Http/Controllers/Api/RentalHireAdminApiController.php',
] as $tenantController) {
    $tenantSource = file_get_contents(__DIR__ . '/../' . $tenantController);
    $assert(str_contains($tenantSource, 'CommerceTenantRuntime'), "{$tenantController} uses tenant runtime");
}

$providerSource = file_get_contents(__DIR__ . '/../System/ChatbotEcommerceServiceProvider.php');
$assert(str_contains($providerSource, "{chatbot:uuid}/session/{sessionId}/inventory/{variant}"), 'public inventory availability is session-authority scoped');
$sessionController = file_get_contents(__DIR__ . '/../System/Http/Controllers/Api/CommerceSessionAuthorityApiController.php');
$assert(str_contains($sessionController, 'Str::uuid()'), 'public session identifiers are generated server-side');
$assert(str_contains($sessionController, 'unset($data[\'customer_id\'], $data[\'session_id\'])'), 'public callers cannot choose signed identities or existing sessions');
$assert(str_contains($sessionMiddleware, 'is not authorised by this session token'), 'unsigned customer identity fields fail closed');

$pricingRuntime = file_get_contents(__DIR__ . '/../System/Services/PricingRuntime.php');
$couponRuntime = file_get_contents(__DIR__ . '/../System/Services/CouponRuntime.php');
$shippingProvider = file_get_contents(__DIR__ . '/../System/Providers/InternalShippingProvider.php');
$assert(str_contains($pricingRuntime, "->where('chatbot_id', (int) \$context['chatbot_id'])"), 'pricing rules are tenant-specific');
$assert(str_contains($couponRuntime, "->where('chatbot_id', \$chatbotId)"), 'coupon lookup is tenant-specific');
$assert(str_contains($shippingProvider, "->where('chatbot_id', \$cart->chatbot_id)"), 'shipping methods are tenant-specific');

$manifest = json_decode(file_get_contents(__DIR__ . '/../extension.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(version_compare((string) ($manifest['version'] ?? '0.0.0'), '4.7.0', '>='), 'manifest preserves v4.7.0 security hardening');
foreach (['commerce.credential-vault', 'commerce.signed-session-authority', 'commerce.tenant-isolation'] as $capability) {
    $assert(in_array($capability, $manifest['capabilities'] ?? [], true), "manifest declares {$capability}");
}
$assert(in_array('ext_chatbot_ecommerce_credentials', $manifest['database']['owned_tables'] ?? [], true), 'manifest owns credential table');

foreach (['extension.json', 'index.json'] as $metadataFile) {
    $metadata = json_decode(file_get_contents(__DIR__ . '/../' . $metadataFile), true, 512, JSON_THROW_ON_ERROR);
    $assert(version_compare((string) ($metadata['version'] ?? '0.0.0'), '4.7.0', '>='), "{$metadataFile} preserves v4.7.0 security hardening");
}

fwrite(STDOUT, "Security hardening contract checks passed: {$checks}\n");
