<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CircuitBreakerDecision
{
    /** @return array{allow:bool,state:string,retry_after:int} */
    public static function evaluate(string $state, int $failureCount, ?int $openedUntilEpoch, ?int $nowEpoch = null): array
    {
        $nowEpoch ??= time();
        $state = strtolower(trim($state));
        if ($state !== 'open') {
            return ['allow' => true, 'state' => $state === 'half_open' ? 'half_open' : 'closed', 'retry_after' => 0];
        }
        if ($openedUntilEpoch !== null && $openedUntilEpoch > $nowEpoch) {
            return ['allow' => false, 'state' => 'open', 'retry_after' => $openedUntilEpoch - $nowEpoch];
        }

        return ['allow' => true, 'state' => 'half_open', 'retry_after' => 0];
    }
}
