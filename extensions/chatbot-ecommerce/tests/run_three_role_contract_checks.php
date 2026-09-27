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
    'database/migrations/2026_08_03_060013_three_role_commerce_communications.php',
    'System/Models/CommerceCommunicationThread.php',
    'System/Models/CommerceCommunicationMessage.php',
    'System/Models/CommerceCommunicationPolicy.php',
    'System/Models/CommerceCommunicationAction.php',
    'System/Models/CommerceEscalation.php',
    'System/Services/CommerceRoleRuntime.php',
    'System/Services/CustomerCommunicationContextRuntime.php',
    'System/Services/CustomerCommunicationRuntime.php',
    'System/Services/CustomerCommunicationCardRuntime.php',
    'System/Http/Controllers/Api/CommerceRoleApiController.php',
    'System/Http/Controllers/Api/CustomerCommunicationApiController.php',
    'System/Http/Controllers/Api/CustomerCommunicationAdminApiController.php',
    'System/Http/Resources/Api/CommerceCommunicationThreadResource.php',
];
foreach ($required as $file) {
    $assert(is_file($root . '/' . $file), "missing {$file}");
}

$migration = file_get_contents($root . '/database/migrations/2026_08_03_060013_three_role_commerce_communications.php');
foreach ([
    'ext_chatbot_commerce_communication_threads',
    'ext_chatbot_commerce_communication_messages',
    'ext_chatbot_commerce_communication_policies',
    'ext_chatbot_commerce_communication_actions',
    'ext_chatbot_commerce_escalations',
] as $table) {
    $assert(str_contains($migration, $table), "migration must create {$table}");
}
$assert(str_contains($migration, "unique(['chatbot_id', 'channel', 'external_message_id']"), 'external messages must be idempotent per chatbot and channel');
$assert(str_contains($migration, "Schema::hasTable"), 'migration must be repeat-safe');

$runtime = file_get_contents($root . '/System/Services/CustomerCommunicationRuntime.php');
foreach (['toolDefinitions', 'ingestMessage', 'threadContext', 'draftReply', 'prepareAction', 'executeApprovedAction', 'handoff'] as $method) {
    $assert(str_contains($runtime, 'function ' . $method), "communications runtime must implement {$method}");
}
$assert(str_contains($runtime, 'CustomerCommunicationAuthority::decision'), 'runtime must enforce the communications authority matrix');
$assert(str_contains($runtime, 'external_message_id'), 'runtime must deduplicate external messages');
$assert(str_contains($runtime, 'CommerceRole::CUSTOMER_COMMUNICATIONS'), 'runtime must enforce customer communications role');

$contextRuntime = file_get_contents($root . '/System/Services/CustomerCommunicationContextRuntime.php');
foreach (['CommerceOrder', 'PaymentIntent', 'Fulfillment', 'CommerceReturn', 'ChatbotCart'] as $model) {
    $assert(str_contains($contextRuntime, $model), "support context must use factual {$model} records");
}
$assert(str_contains($contextRuntime, 'if (! $identityVerified)'), 'private order and payment context must be withheld until identity verification');

$publicController = file_get_contents($root . '/System/Http/Controllers/Api/CustomerCommunicationApiController.php');
$assert(str_contains($publicController, '$validated[\'identity_verified\'] = false'), 'public message ingestion must not allow self-asserted identity verification');
$adminController = file_get_contents($root . '/System/Http/Controllers/Api/CustomerCommunicationAdminApiController.php');
$assert(str_contains($adminController, 'function updateThread'), 'authenticated sellers must be able to verify and assign support threads');

$provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');
foreach (['CommerceRoleApiController', 'CustomerCommunicationApiController', 'CustomerCommunicationAdminApiController'] as $controller) {
    $assert(str_contains($provider, $controller), "provider must register {$controller}");
}
foreach (['commerce/roles', 'support/messages', 'support/threads', 'actions/{actionUuid}/execute', '/escalations', 'support/policies'] as $route) {
    $assert(str_contains($provider, $route), "provider must register route fragment {$route}");
}

$config = file_get_contents($root . '/config/platform-quality.php');
foreach (['three_role_commerce', 'customer_communications', 'automatic_refund_limit', 'auto_reply_confidence', 'human_handoff'] as $token) {
    $assert(str_contains($config, $token), "config must declare {$token}");
}

$manifest = json_decode((string) file_get_contents($root . '/extension.manifest.json'), true);
$assert(is_array($manifest), 'sidecar manifest must remain valid JSON');
$manifestText = json_encode($manifest);
foreach (['three_role_commerce', 'customer_communications', 'ext_chatbot_commerce_communication_threads', 'ext_chatbot_commerce_escalations'] as $token) {
    $assert(str_contains((string) $manifestText, $token), "manifest must declare {$token}");
}

foreach (['extension.json', 'index.json'] as $metadataFile) {
    $metadata = json_decode((string) file_get_contents($root . '/' . $metadataFile), true);
    $assert(version_compare((string) ($metadata['version'] ?? '0.0.0'), '4.1.0', '>='), "{$metadataFile} must remain at or above 4.1.0");
}
$upgrade = file_get_contents($root . '/upgrade.php');
$assert((bool) preg_match("/'version'=>'([^']+)'/", $upgrade, $match) && version_compare($match[1], '4.1.0', '>='), 'upgrade script must preserve three-role commerce 4.1.0+');

$readme = file_get_contents($root . '/README.md');
foreach (['Customer Shopping Assistant', 'Seller Commerce Steward', 'Customer Communications Agent', 'Answer automatically', 'Human-only'] as $token) {
    $assert(str_contains($readme, $token), "README must document {$token}");
}

$openapi = file_get_contents($root . '/openapi/conversational-commerce-v1.yaml');
foreach (['/commerce/roles', '/support/messages', '/support/threads', '/escalations', 'customer_communications'] as $token) {
    $assert(str_contains($openapi, $token), "OpenAPI must declare {$token}");
}

$toolService = file_get_contents($root . '/System/Services/EcommerceToolService.php');
$assert(str_contains($toolService, 'CustomerCommunicationRuntime'), 'tool service must bridge the customer communications tools');
$assert(str_contains($toolService, 'CUSTOMER_COMMUNICATIONS'), 'tool service must scope communications tools by role');

echo "Three-role contract checks passed: {$checks}\n";
