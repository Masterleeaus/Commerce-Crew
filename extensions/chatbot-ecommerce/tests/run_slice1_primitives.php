<?php

declare(strict_types=1);

require __DIR__ . '/../System/Support/ConversationContextStack.php';
require __DIR__ . '/../System/Support/ErrorLexicon.php';
require __DIR__ . '/../System/Support/BudgetLock.php';
require __DIR__ . '/../System/Support/CommerceUiFallback.php';
require __DIR__ . '/../System/Support/OrderLifecycle.php';
require __DIR__ . '/../System/Support/ReturnLifecycle.php';

use App\Extensions\ChatbotEcommerce\System\Support\BudgetLock;
use App\Extensions\ChatbotEcommerce\System\Support\CommerceUiFallback;
use App\Extensions\ChatbotEcommerce\System\Support\ConversationContextStack;
use App\Extensions\ChatbotEcommerce\System\Support\ErrorLexicon;
use App\Extensions\ChatbotEcommerce\System\Support\OrderLifecycle;
use App\Extensions\ChatbotEcommerce\System\Support\ReturnLifecycle;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$context = ConversationContextStack::merge(
    ['current_product_ids' => [11, 12], 'last_filters' => ['colour' => 'blue']],
    ['current_product_ids' => [12, 13], 'last_filters' => ['size' => 'M'], 'selected_product_id' => 13]
);
$assert($context['current_product_ids'] === [12, 13], 'context should replace active result set deterministically');
$assert($context['last_filters'] === ['colour' => 'blue', 'size' => 'M'], 'context should merge filters');
$assert(ConversationContextStack::requireProductReference($context, null) === 13, 'selected product should resolve pronouns');
$ambiguous = ['current_product_ids' => [11, 12], 'selected_product_id' => null];
$assert(ConversationContextStack::requireProductReference($ambiguous, null) === null, 'ambiguous references must not guess');

$lexicon = new ErrorLexicon([
    'ebay:8542' => [
        'message' => 'The barcode is not valid for this category.',
        'remediation' => 'Check the UPC/GTIN or remove it when the category permits.',
        'retryable' => false,
    ],
]);
$translated = $lexicon->translate('ebay', '8542', 'Invalid GTIN stack trace secret=abc');
$assert($translated['message'] === 'The barcode is not valid for this category.', 'known API errors should be human-readable');
$assert(! str_contains($translated['message'], 'secret='), 'raw provider errors must not leak into user text');
$fallback = $lexicon->translate('unknown', '500', 'private raw failure');
$assert($fallback['message'] === 'The commerce provider could not complete that action.', 'unknown errors should use safe fallback');

$within = BudgetLock::evaluate(9000, 'AUD', [
    'max_order_value' => 10000,
    'daily_spend_limit' => 25000,
    'spent_today' => 12000,
    'currency' => 'AUD',
]);
$assert($within['allowed'] === true, 'spend inside limits should be allowed');
$over = BudgetLock::evaluate(14000, 'AUD', [
    'max_order_value' => 10000,
    'daily_spend_limit' => 25000,
    'spent_today' => 12000,
    'currency' => 'AUD',
]);
$assert($over['allowed'] === false && $over['reason'] === 'max_order_value', 'per-order budget must block excess');
$daily = BudgetLock::evaluate(9000, 'AUD', [
    'max_order_value' => 10000,
    'daily_spend_limit' => 20000,
    'spent_today' => 12000,
    'currency' => 'AUD',
]);
$assert($daily['allowed'] === false && $daily['reason'] === 'daily_spend_limit', 'daily budget must block excess');

$text = CommerceUiFallback::numberedChoices([
    ['title' => 'Vacuum A', 'url' => 'https://example.test/a', 'price' => '$299'],
    ['title' => 'Vacuum B', 'url' => 'https://example.test/b', 'price' => '$349'],
]);
$assert(str_contains($text, 'Reply 1') && str_contains($text, 'https://example.test/a'), 'fallback must include numbered clickable choices');

$assert(OrderLifecycle::canTransition('pending', 'confirmed'), 'pending order can confirm');
$assert(! OrderLifecycle::canTransition('completed', 'pending'), 'completed order cannot regress');
$assert(ReturnLifecycle::canTransition('requested', 'approved'), 'return can be approved');
$assert(! ReturnLifecycle::canTransition('refunded', 'requested'), 'refunded return cannot reopen directly');

echo "Slice 1 primitive checks passed: {$checks}\n";
