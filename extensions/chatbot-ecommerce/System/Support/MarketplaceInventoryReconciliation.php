<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use DateTimeImmutable;
use Throwable;

final class MarketplaceInventoryReconciliation
{
    /**
     * @param array<string,mixed> $canonical
     * @param array<string,mixed> $channel
     * @param array<string,mixed> $policy
     * @return array<string,mixed>
     */
    public static function assess(array $canonical, array $channel, array $policy, string $now = 'now'): array
    {
        $target = max(0, (int) ($policy['target_quantity'] ?? $canonical['available'] ?? 0));
        $external = max(0, (int) ($channel['quantity'] ?? 0));
        $tolerance = max(0, (int) ($policy['quantity_tolerance'] ?? 0));
        $maximumAge = max(30, (int) ($policy['maximum_sync_age_seconds'] ?? 300));
        $observedAt = trim((string) ($channel['observed_at'] ?? ''));
        $snapshotFresh = false;
        $ageSeconds = null;

        if ($observedAt !== '') {
            try {
                $nowAt = new DateTimeImmutable($now);
                $observed = new DateTimeImmutable($observedAt);
                $ageSeconds = max(0, $nowAt->getTimestamp() - $observed->getTimestamp());
                $snapshotFresh = $ageSeconds <= $maximumAge;
            } catch (Throwable) {
                $snapshotFresh = false;
            }
        }

        $base = [
            'target_quantity' => $target,
            'external_quantity' => $external,
            'delta' => $target - $external,
            'canonical_version' => (int) ($canonical['version'] ?? 0),
            'canonical_available' => max(0, (int) ($canonical['available'] ?? 0)),
            'reserved' => max(0, (int) ($canonical['reserved'] ?? 0)),
            'committed' => max(0, (int) ($canonical['committed'] ?? 0)),
            'source_hash' => (string) ($channel['source_hash'] ?? ''),
            'snapshot_fresh' => $snapshotFresh,
            'snapshot_age_seconds' => $ageSeconds,
            'maximum_sync_age_seconds' => $maximumAge,
        ];

        if (! $snapshotFresh) {
            return $base + [
                'conflict' => true,
                'blocking' => true,
                'code' => 'external_state_stale',
                'severity' => 'critical',
                'message' => 'The marketplace inventory observation is too old for a safe correction. Refresh the listing first.',
            ];
        }

        $difference = $external - $target;
        if (abs($difference) <= $tolerance) {
            return $base + [
                'conflict' => false,
                'blocking' => false,
                'code' => 'in_sync',
                'severity' => 'none',
                'message' => 'Marketplace inventory matches the permitted channel allocation.',
            ];
        }

        if ($external > $target) {
            return $base + [
                'conflict' => true,
                'blocking' => true,
                'code' => 'external_above_target',
                'severity' => 'critical',
                'oversell_exposure' => $external - $target,
                'message' => 'Marketplace inventory exceeds the reservation-aware channel allocation and may oversell.',
            ];
        }

        return $base + [
            'conflict' => true,
            'blocking' => false,
            'code' => 'external_below_target',
            'severity' => 'warning',
            'undersell_quantity' => $target - $external,
            'message' => 'Marketplace inventory is lower than the permitted channel allocation and may suppress sales.',
        ];
    }
}
