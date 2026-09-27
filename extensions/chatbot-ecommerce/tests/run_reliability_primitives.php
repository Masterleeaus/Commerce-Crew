<?php

declare(strict_types=1);

require_once __DIR__ . '/../System/Support/RetryBackoff.php';
require_once __DIR__ . '/../System/Support/CircuitBreakerDecision.php';
require_once __DIR__ . '/../System/Support/LifecycleStatus.php';

use App\Extensions\ChatbotEcommerce\System\Support\CircuitBreakerDecision;
use App\Extensions\ChatbotEcommerce\System\Support\LifecycleStatus;
use App\Extensions\ChatbotEcommerce\System\Support\RetryBackoff;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(RetryBackoff::seconds(1, 5, 300) === 5, 'first retry uses base delay');
$assert(RetryBackoff::seconds(2, 5, 300) === 10, 'second retry doubles delay');
$assert(RetryBackoff::seconds(8, 5, 300) === 300, 'retry delay is capped');
$assert(RetryBackoff::schedule(4, 5, 300) === [5, 10, 20, 40], 'retry schedule is deterministic');

$closed = CircuitBreakerDecision::evaluate('closed', 0, null, 1000);
$assert($closed['allow'], 'closed circuit permits calls');
$open = CircuitBreakerDecision::evaluate('open', 5, 1200, 1000);
$assert(! $open['allow'] && $open['retry_after'] === 200, 'open circuit blocks before recovery');
$half = CircuitBreakerDecision::evaluate('open', 5, 900, 1000);
$assert($half['allow'] && $half['state'] === 'half_open', 'expired open circuit permits one recovery probe');

$assert(LifecycleStatus::canServe('enabled'), 'enabled extension serves requests');
$assert(! LifecycleStatus::canServe('disabled'), 'disabled extension rejects requests');
$assert(! LifecycleStatus::canServe('uninstalling'), 'uninstalling extension rejects requests');
$assert(LifecycleStatus::preservesData('disabled'), 'disable preserves data');
$assert(LifecycleStatus::preservesData('uninstalled'), 'default uninstall preserves data');

fwrite(STDOUT, "Reliability primitive checks passed: {$checks}\n");
