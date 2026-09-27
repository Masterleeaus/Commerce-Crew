<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use DateTimeImmutable;
use DateTimeInterface;

final class MarketplaceConflictDetector
{
    /**
     * @param array<string,mixed> $listing
     * @param array<string,mixed> $changes
     * @param array<string,mixed> $options
     * @return array{blocking:bool,conflicts:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>}
     */
    public static function assess(array $listing, string $expectedSourceHash, string $operation, array $changes, array $options = []): array
    {
        $conflicts = [];
        $warnings = [];
        $actualHash = (string) ($listing['source_hash'] ?? '');
        if ($expectedSourceHash === '' || $actualHash === '' || ! hash_equals($actualHash, $expectedSourceHash)) {
            $conflicts[] = [
                'code' => 'source_version_changed',
                'message' => 'The marketplace listing changed after it was reviewed. Refresh it before applying the update.',
                'expected_source_hash' => $expectedSourceHash,
                'actual_source_hash' => $actualHash,
            ];
        }

        $maximumAge = max(30, (int) ($options['maximum_snapshot_age_seconds'] ?? 300));
        $lastSeen = $listing['last_seen_at'] ?? null;
        if ($lastSeen !== null && $lastSeen !== '') {
            $now = new DateTimeImmutable((string) ($options['now'] ?? 'now'));
            $observed = $lastSeen instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($lastSeen) : new DateTimeImmutable((string) $lastSeen);
            if (($now->getTimestamp() - $observed->getTimestamp()) > $maximumAge) {
                array_unshift($conflicts, [
                    'code' => 'stale_snapshot',
                    'message' => 'The marketplace listing snapshot is too old for a safe write. Refresh it first.',
                    'maximum_age_seconds' => $maximumAge,
                    'age_seconds' => $now->getTimestamp() - $observed->getTimestamp(),
                ]);
            }
        } else {
            $conflicts[] = ['code' => 'missing_snapshot_time', 'message' => 'The listing has no verified observation time. Refresh it first.'];
        }

        if (MarketplaceWriteOperation::capability($operation) === MarketplaceWriteOperation::UPDATE_INVENTORY) {
            $internalAvailable = $options['internal_available'] ?? null;
            if ($internalAvailable === null) {
                $warnings[] = ['code' => 'inventory_not_mapped', 'message' => 'This marketplace listing is not mapped to internal inventory.'];
            } elseif (! (bool) ($options['allow_oversell'] ?? false) && (int) ($changes['quantity'] ?? 0) > (int) $internalAvailable) {
                $conflicts[] = [
                    'code' => 'inventory_exceeds_available',
                    'message' => 'The requested marketplace quantity exceeds available internal stock.',
                    'requested_quantity' => (int) ($changes['quantity'] ?? 0),
                    'internal_available' => (int) $internalAvailable,
                ];
            }
        }

        return ['blocking' => $conflicts !== [], 'conflicts' => $conflicts, 'warnings' => $warnings];
    }
}
