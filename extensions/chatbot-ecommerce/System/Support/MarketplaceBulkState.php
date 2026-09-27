<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MarketplaceBulkState
{
    public const DRAFT = 'draft';
    public const READY = 'ready';
    public const CONFLICT_BLOCKED = 'conflict_blocked';
    public const APPROVED = 'approved';
    public const RUNNING = 'running';
    public const PARTIALLY_COMPLETED = 'partially_completed';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const PARTIALLY_ROLLED_BACK = 'partially_rolled_back';
    public const ROLLED_BACK = 'rolled_back';
    public const CANCELLED = 'cancelled';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [self::DRAFT,self::READY,self::CONFLICT_BLOCKED,self::APPROVED,self::RUNNING,self::PARTIALLY_COMPLETED,self::COMPLETED,self::FAILED,self::PARTIALLY_ROLLED_BACK,self::ROLLED_BACK,self::CANCELLED];
    }
}
