<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;

final class MarketplaceWriteOperation
{
    public const UPDATE_LISTING = 'update_listing';
    public const UPDATE_PRICE = 'update_price';
    public const UPDATE_INVENTORY = 'update_inventory';
    public const PAUSE_LISTING = 'pause_listing';
    public const RESUME_LISTING = 'resume_listing';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [self::UPDATE_LISTING, self::UPDATE_PRICE, self::UPDATE_INVENTORY, self::PAUSE_LISTING, self::RESUME_LISTING];
    }

    public static function capability(string $operation): string
    {
        $operation = strtolower(trim($operation));
        if (! in_array($operation, self::all(), true)) {
            throw new InvalidArgumentException("Unsupported marketplace write operation: {$operation}");
        }

        return $operation;
    }

    public static function requiresChanges(string $operation): bool
    {
        return ! in_array(self::capability($operation), [self::PAUSE_LISTING, self::RESUME_LISTING], true);
    }
}
