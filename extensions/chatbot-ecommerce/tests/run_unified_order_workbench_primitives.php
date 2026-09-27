<?php

declare(strict_types=1);

require_once __DIR__ . '/../System/Support/UnifiedOrderSource.php';
require_once __DIR__ . '/../System/Support/SettlementReconciliation.php';
require_once __DIR__ . '/../System/Support/OrderExceptionDetector.php';
require_once __DIR__ . '/../System/Support/OrderWorkbenchAction.php';

use App\Extensions\ChatbotEcommerce\System\Support\UnifiedOrderSource;
use App\Extensions\ChatbotEcommerce\System\Support\SettlementReconciliation;
use App\Extensions\ChatbotEcommerce\System\Support\OrderExceptionDetector;
use App\Extensions\ChatbotEcommerce\System\Support\OrderWorkbenchAction;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(UnifiedOrderSource::normalize('SHOPIFY') === 'shopify', 'source normalization is lowercase');
$assert(UnifiedOrderSource::isExternal('amazon'), 'marketplace orders are external');
$assert(! UnifiedOrderSource::isExternal('native'), 'native orders are internal');
$assert(UnifiedOrderSource::externalAuthoritative('woocommerce'), 'external platform snapshot remains authoritative');

$reconciliation = SettlementReconciliation::calculate([
    ['type' => 'sale', 'amount' => 10000],
    ['type' => 'fee', 'amount' => -1200],
    ['type' => 'shipping_cost', 'amount' => -500],
    ['type' => 'refund', 'amount' => -1000],
    ['type' => 'payout', 'amount' => 7300],
]);
$assert($reconciliation['gross_amount'] === 10000, 'gross amount is calculated');
$assert($reconciliation['fee_amount'] === 1200, 'fees are represented as positive cost');
$assert($reconciliation['expected_net_amount'] === 7300, 'expected net payout is calculated');
$assert($reconciliation['reported_net_amount'] === 7300, 'reported payout is captured');
$assert($reconciliation['variance_amount'] === 0 && $reconciliation['status'] === 'reconciled', 'matching payout reconciles');

$exceptions = OrderExceptionDetector::detect([
    'payment_status' => 'failed',
    'fulfillment_status' => 'unfulfilled',
    'placed_at_timestamp' => 1000,
    'shipping_address' => ['country' => 'AU'],
    'stock_conflict' => true,
    'return_disputed' => true,
    'reconciliation_status' => 'variance',
], 1000 + 172800, 86400);
$types = array_column($exceptions, 'type');
foreach (['invalid_address', 'payment_failure', 'fulfillment_delay', 'stock_conflict', 'disputed_return', 'settlement_variance'] as $type) {
    $assert(in_array($type, $types, true), "{$type} exception is detected");
}

$assert(OrderWorkbenchAction::requiresApproval('refund_proposal'), 'refund proposal requires seller approval');
$assert(! OrderWorkbenchAction::requiresApproval('acknowledge'), 'acknowledgement is low risk');
$assert(OrderWorkbenchAction::isSupported('prepare_customer_contact'), 'customer contact preparation is supported');

fwrite(STDOUT, "Unified order workbench primitive checks passed: {$checks}\n");
