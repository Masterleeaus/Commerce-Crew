<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/System/Support/MarketplaceCapability.php';
require $root . '/System/Support/MarketplaceWriteOperation.php';
require $root . '/System/Support/MarketplaceWritePatch.php';
require $root . '/System/Support/MarketplaceWriteState.php';
require $root . '/System/Support/MarketplaceWriteApprovalToken.php';
require $root . '/System/Support/MarketplaceConflictDetector.php';

use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceCapability;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceConflictDetector;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteApprovalToken;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteOperation;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWritePatch;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteState;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert(MarketplaceCapability::isWriteAllowed('update_price'), 'update_price must be a supported write capability');
$assert(! MarketplaceCapability::isWriteAllowed('refund_order'), 'refund_order must remain outside this pass');
$assert(MarketplaceWriteOperation::capability('update_inventory') === 'update_inventory', 'inventory capability mismatch');
$assert(MarketplaceWriteOperation::requiresChanges('pause_listing') === false, 'pause should not require a patch');

$price = MarketplaceWritePatch::sanitize('update_price', ['price_amount' => '1299', 'currency' => 'aud', 'title' => 'ignored']);
$assert($price === ['price_amount' => 1299, 'currency' => 'AUD'], 'price patch must be normalized and field-limited');
$inventory = MarketplaceWritePatch::sanitize('update_inventory', ['quantity' => 4, 'allow_oversell' => true]);
$assert($inventory === ['quantity' => 4], 'client must not be able to inject oversell authority');
$assert(strlen(MarketplaceWritePatch::hash('update_price', $price)) === 64, 'patch hash must be sha256');

$assert(MarketplaceWriteState::canTransition('prepared', 'approved'), 'prepared should transition to approved');
$assert(! MarketplaceWriteState::canTransition('executed', 'approved'), 'executed must not transition back to approved');
$assert(MarketplaceWriteState::isTerminal('rolled_back'), 'rolled_back must be terminal');

$secret = 'marketplace-write-test-secret';
$token = MarketplaceWriteApprovalToken::issue([
    'proposal_uuid' => 'proposal-1',
    'action_hash' => str_repeat('a', 64),
    'chatbot_id' => 12,
    'user_id' => 34,
], $secret, 300, 1_800_000_000);
$payload = MarketplaceWriteApprovalToken::verify($token, $secret, 1_800_000_100);
$assert($payload['proposal_uuid'] === 'proposal-1', 'approval token proposal mismatch');
$assert($payload['user_id'] === 34, 'approval token user mismatch');
$assert(isset($payload['jti']) && $payload['jti'] !== '', 'approval token must have a nonce');

$badTokenRejected = false;
try {
    MarketplaceWriteApprovalToken::verify($token . 'x', $secret, 1_800_000_100);
} catch (Throwable) {
    $badTokenRejected = true;
}
$assert($badTokenRejected, 'tampered approval token must be rejected');

$conflicts = MarketplaceConflictDetector::assess(
    ['source_hash' => str_repeat('b', 64), 'last_seen_at' => '2026-08-03T00:00:00+00:00', 'snapshot' => ['attributes' => ['sku' => 'SKU-1']]],
    str_repeat('c', 64),
    'update_inventory',
    ['quantity' => 8],
    ['now' => '2026-08-03T00:01:00+00:00', 'maximum_snapshot_age_seconds' => 300, 'internal_available' => 5, 'allow_oversell' => false]
);
$assert($conflicts['blocking'] === true, 'hash and inventory conflicts must block');
$assert(count($conflicts['conflicts']) === 2, 'expected source and inventory conflicts');

$stale = MarketplaceConflictDetector::assess(
    ['source_hash' => str_repeat('d', 64), 'last_seen_at' => '2026-08-03T00:00:00+00:00', 'snapshot' => []],
    str_repeat('d', 64),
    'update_price',
    ['price_amount' => 1000, 'currency' => 'AUD'],
    ['now' => '2026-08-03T00:10:01+00:00', 'maximum_snapshot_age_seconds' => 600]
);
$assert($stale['blocking'] === true, 'stale source data must block writes');
$assert($stale['conflicts'][0]['code'] === 'stale_snapshot', 'stale conflict code mismatch');

$clear = MarketplaceConflictDetector::assess(
    ['source_hash' => str_repeat('e', 64), 'last_seen_at' => '2026-08-03T00:00:00+00:00', 'snapshot' => []],
    str_repeat('e', 64),
    'update_inventory',
    ['quantity' => 3],
    ['now' => '2026-08-03T00:01:00+00:00', 'maximum_snapshot_age_seconds' => 300, 'internal_available' => 5, 'allow_oversell' => false]
);
$assert($clear['blocking'] === false, 'fresh safe inventory write should not block');

fwrite(STDOUT, "Marketplace write primitives: {$checks} checks passed.\n");
