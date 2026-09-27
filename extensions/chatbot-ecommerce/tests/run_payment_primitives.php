<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/System/Support/PaymentStatus.php';
require $root . '/System/Support/PaymentLifecycle.php';
require $root . '/System/Support/PaymentWebhookVerifier.php';

use App\Extensions\ChatbotEcommerce\System\Support\PaymentLifecycle;
use App\Extensions\ChatbotEcommerce\System\Support\PaymentStatus;
use App\Extensions\ChatbotEcommerce\System\Support\PaymentWebhookVerifier;

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(PaymentLifecycle::canTransition(PaymentStatus::PENDING, PaymentStatus::REQUIRES_ACTION), 'pending can require customer action');
$assert(PaymentLifecycle::canTransition(PaymentStatus::PENDING, PaymentStatus::AUTHORIZED), 'pending can be authorised');
$assert(PaymentLifecycle::canTransition(PaymentStatus::AUTHORIZED, PaymentStatus::CAPTURED), 'authorised payment can be captured');
$assert(PaymentLifecycle::canTransition(PaymentStatus::AUTHORIZED, PaymentStatus::PARTIALLY_CAPTURED), 'authorised payment can be partially captured');
$assert(PaymentLifecycle::canTransition(PaymentStatus::PARTIALLY_CAPTURED, PaymentStatus::CAPTURED), 'partial capture can be completed');
$assert(PaymentLifecycle::canTransition(PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_REFUNDED), 'captured payment can be partially refunded');
$assert(PaymentLifecycle::canTransition(PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED), 'partial refund can become fully refunded');
$assert(! PaymentLifecycle::canTransition(PaymentStatus::CAPTURED, PaymentStatus::AUTHORIZED), 'captured payment cannot move backwards');
$assert(! PaymentLifecycle::canTransition(PaymentStatus::REFUNDED, PaymentStatus::CAPTURED), 'refunded payment is terminal');
$assert(PaymentLifecycle::remainingCapture(10000, 2500) === 7500, 'remaining capture is calculated in minor units');
$assert(PaymentLifecycle::remainingRefund(10000, 2500) === 7500, 'remaining refund is calculated in minor units');
$assert(PaymentLifecycle::refundStatus(10000, 2500) === PaymentStatus::PARTIALLY_REFUNDED, 'partial refund status is selected');
$assert(PaymentLifecycle::refundStatus(10000, 10000) === PaymentStatus::REFUNDED, 'full refund status is selected');

$payload = '{"event":"payment.captured","id":"evt_123"}';
$secret = 'test-secret';
$timestamp = 1784678400;
$signature = PaymentWebhookVerifier::sign($payload, $secret, $timestamp);
$assert(PaymentWebhookVerifier::verify($payload, $signature, $secret, $timestamp, $timestamp, 300), 'valid signed webhook is accepted');
$assert(! PaymentWebhookVerifier::verify($payload . 'x', $signature, $secret, $timestamp, $timestamp, 300), 'modified webhook is rejected');
$assert(! PaymentWebhookVerifier::verify($payload, $signature, $secret, $timestamp, $timestamp + 301, 300), 'stale webhook is rejected');

$event = PaymentWebhookVerifier::eventKey('internal', 'evt_123', $payload);
$assert($event === PaymentWebhookVerifier::eventKey('internal', 'evt_123', $payload), 'webhook event keys are deterministic');
$assert($event !== PaymentWebhookVerifier::eventKey('internal', 'evt_124', $payload), 'webhook event IDs are isolated');
$assert($event === PaymentWebhookVerifier::eventKey('internal', 'evt_123', $payload . ' changed'), 'provider event IDs remain idempotent across payload retries');

echo "Payment primitive checks passed: {$tests}\n";
