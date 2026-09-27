<?php

declare(strict_types=1);

require_once __DIR__ . '/../System/Support/CommerceSessionAuthority.php';
require_once __DIR__ . '/../System/Support/CredentialRedactor.php';
require_once __DIR__ . '/../System/Support/CommercePermissionMap.php';

use App\Extensions\ChatbotEcommerce\System\Support\CommercePermissionMap;
use App\Extensions\ChatbotEcommerce\System\Support\CommerceSessionAuthority;
use App\Extensions\ChatbotEcommerce\System\Support\CredentialRedactor;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$secret = str_repeat('s', 48);
$token = CommerceSessionAuthority::issue([
    'chatbot_id' => 17,
    'chatbot_uuid' => 'bot-uuid',
    'session_id' => 'session-12345678901234567890',
    'capabilities' => ['catalogue:read', 'cart:write'],
    'customer_id' => 42,
], $secret, 300, 1_000);
$claims = CommerceSessionAuthority::verify($token, $secret, 1_100);
$assert($claims['chatbot_id'] === 17, 'token binds chatbot id');
$assert($claims['session_id'] === 'session-12345678901234567890', 'token binds session id');
$assert(in_array('cart:write', $claims['capabilities'], true), 'token retains capabilities');
$assert(CommerceSessionAuthority::allows($claims, 'catalogue:read'), 'allows declared capability');
$assert(! CommerceSessionAuthority::allows($claims, 'checkout:execute'), 'rejects undeclared capability');

$tampered = substr($token, 0, -1) . (substr($token, -1) === 'A' ? 'B' : 'A');
$failed = false;
try { CommerceSessionAuthority::verify($tampered, $secret, 1_100); } catch (Throwable) { $failed = true; }
$assert($failed, 'tampered token fails closed');
$failed = false;
try { CommerceSessionAuthority::verify($token, $secret, 1_301); } catch (Throwable) { $failed = true; }
$assert($failed, 'expired token fails closed');

$payload = [
    'shopify_access_token' => 'secret-token',
    'nested' => [
        'woocommerce_consumer_key' => 'ck_secret',
        'safe' => 'visible',
        'consumer_secret' => 'must-hide',
    ],
];
$redacted = CredentialRedactor::redact($payload);
$assert($redacted['shopify_access_token'] === '[REDACTED]', 'redacts Shopify token');
$assert($redacted['nested']['woocommerce_consumer_key'] === '[REDACTED]', 'redacts Woo key');
$assert($redacted['nested']['consumer_secret'] === '[REDACTED]', 'redacts generic secret');
$assert($redacted['nested']['safe'] === 'visible', 'preserves safe values');
$assert(CredentialRedactor::containsSecretKey(['api_key' => 'x']), 'detects secret keys');

$assert(CommercePermissionMap::forRoute('api.v3.chatbot.ecommerce.admin.payments.capture') === 'chatbot-ecommerce.manage-payments', 'maps payment route');
$assert(CommercePermissionMap::forRoute('api.v3.chatbot.ecommerce.admin.marketplace-write-execute') === 'chatbot-ecommerce.execute-marketplace-writes', 'maps marketplace write route');
$assert(CommercePermissionMap::forRoute('unknown.route') === 'chatbot-ecommerce.read', 'unknown admin route uses least privilege read permission');

fwrite(STDOUT, "Security hardening primitive checks passed: {$checks}\n");
