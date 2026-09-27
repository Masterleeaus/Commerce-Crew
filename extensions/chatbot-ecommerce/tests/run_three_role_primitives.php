<?php

declare(strict_types=1);

require __DIR__ . '/../System/Support/CommerceRole.php';
require __DIR__ . '/../System/Support/CommerceRoleRouter.php';
require __DIR__ . '/../System/Support/CustomerCommunicationAuthority.php';

use App\Extensions\ChatbotEcommerce\System\Support\CommerceRole;
use App\Extensions\ChatbotEcommerce\System\Support\CommerceRoleRouter;
use App\Extensions\ChatbotEcommerce\System\Support\CustomerCommunicationAuthority;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(CommerceRoleRouter::resolve(['actor_type' => 'customer']) === CommerceRole::SHOPPING_ASSISTANT, 'customer storefront sessions resolve to shopping assistant');
$assert(CommerceRoleRouter::resolve(['actor_type' => 'customer', 'channel' => 'whatsapp', 'intent' => 'order_status']) === CommerceRole::CUSTOMER_COMMUNICATIONS, 'customer support messages resolve to customer communications');
$assert(CommerceRoleRouter::resolve(['actor_type' => 'seller', 'authenticated' => true]) === CommerceRole::SELLER_STEWARD, 'authenticated sellers resolve to seller steward');
$assert(CommerceRoleRouter::resolve(['actor_type' => 'customer', 'requested_role' => CommerceRole::SELLER_STEWARD]) === CommerceRole::SHOPPING_ASSISTANT, 'customers cannot self-elevate into seller steward');
$assert(CommerceRoleRouter::resolve(['actor_type' => 'unknown', 'requested_role' => CommerceRole::SELLER_STEWARD]) === CommerceRole::SHOPPING_ASSISTANT, 'unknown actors fail closed to shopping assistant');

$answer = CustomerCommunicationAuthority::decision('answer_product_question', ['confidence' => 0.96]);
$assert($answer['level'] === 'answer_automatically' && $answer['allowed'] === true, 'high-confidence factual product answers may be automatic');

$tracking = CustomerCommunicationAuthority::decision('get_order_status', ['identity_verified' => true, 'confidence' => 0.91]);
$assert($tracking['level'] === 'answer_automatically' && $tracking['allowed'] === true, 'verified factual order status may be automatic');

$refund = CustomerCommunicationAuthority::decision('issue_refund', ['amount' => 5000, 'automatic_refund_limit' => 2500]);
$assert($refund['level'] === 'require_approval' && $refund['allowed'] === false, 'refunds above configured limits require approval');

$smallRefund = CustomerCommunicationAuthority::decision('issue_refund', ['amount' => 1500, 'automatic_refund_limit' => 2500, 'identity_verified' => true]);
$assert($smallRefund['level'] === 'execute_within_limits' && $smallRefund['allowed'] === true, 'verified refunds inside explicit limits may execute');

$legal = CustomerCommunicationAuthority::decision('legal_threat', []);
$assert($legal['level'] === 'human_only' && $legal['escalate'] === true, 'legal threats are human-only');

$fraud = CustomerCommunicationAuthority::decision('fraud_suspected', []);
$assert($fraud['level'] === 'human_only' && $fraud['escalate'] === true, 'fraud decisions are human-only');

$unknown = CustomerCommunicationAuthority::decision('unknown_action', []);
$assert($unknown['level'] === 'require_approval' && $unknown['allowed'] === false, 'unknown actions fail closed to approval');

echo "Three-role primitive checks passed: {$checks}\n";
