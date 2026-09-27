<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MarketplaceWriteState
{
    public const PREPARED = 'prepared';
    public const CONFLICT_BLOCKED = 'conflict_blocked';
    public const APPROVED = 'approved';
    public const QUEUED = 'queued';
    public const EXECUTING = 'executing';
    public const EXECUTED = 'executed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const ROLLED_BACK = 'rolled_back';
    public const ROLLBACK_FAILED = 'rollback_failed';

    /** @var array<string,array<int,string>> */
    private const TRANSITIONS = [
        self::PREPARED => [self::APPROVED, self::CANCELLED, self::CONFLICT_BLOCKED],
        self::CONFLICT_BLOCKED => [self::PREPARED, self::CANCELLED],
        self::APPROVED => [self::QUEUED, self::EXECUTING, self::CANCELLED, self::CONFLICT_BLOCKED],
        self::QUEUED => [self::EXECUTING, self::FAILED, self::CANCELLED, self::CONFLICT_BLOCKED],
        self::EXECUTING => [self::EXECUTED, self::FAILED, self::CONFLICT_BLOCKED],
        self::EXECUTED => [self::ROLLED_BACK, self::ROLLBACK_FAILED],
        self::FAILED => [self::APPROVED, self::CANCELLED],
        self::ROLLBACK_FAILED => [self::ROLLED_BACK],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $state): bool
    {
        return in_array($state, [self::CANCELLED, self::ROLLED_BACK], true);
    }
}
