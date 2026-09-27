<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class InventoryConflictResolutionPolicy
{
    /**
     * @param array<string,mixed> $policy
     * @param array<string,mixed> $assessment
     * @return array{auto_prepare:bool,auto_execute:bool,reason:string}
     */
    public static function evaluate(array $policy, array $assessment): array
    {
        $mode = strtolower(trim((string) ($policy['resolution_mode'] ?? 'suggest_only')));
        if ($mode !== 'auto_prepare') {
            return ['auto_prepare' => false, 'auto_execute' => false, 'reason' => 'The policy does not permit automatic preparation.'];
        }
        if (! (bool) ($assessment['conflict'] ?? true)) {
            return ['auto_prepare' => false, 'auto_execute' => false, 'reason' => 'No correction is required.'];
        }
        if ((bool) ($assessment['blocking'] ?? false)) {
            return ['auto_prepare' => false, 'auto_execute' => false, 'reason' => 'The conflict requires a fresh or manual review.'];
        }
        if ((bool) ($policy['require_fresh_snapshot'] ?? true) && ! (bool) ($assessment['snapshot_fresh'] ?? false)) {
            return ['auto_prepare' => false, 'auto_execute' => false, 'reason' => 'The marketplace observation is stale.'];
        }
        $maximumDelta = max(0, (int) ($policy['maximum_auto_delta'] ?? 0));
        if ($maximumDelta < 1 || abs((int) ($assessment['delta'] ?? 0)) > $maximumDelta) {
            return ['auto_prepare' => false, 'auto_execute' => false, 'reason' => 'The quantity change exceeds the automatic preparation limit.'];
        }

        return [
            'auto_prepare' => true,
            'auto_execute' => false,
            'reason' => 'A bounded correction may be prepared, but seller approval is still required.',
        ];
    }
}
