<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MarketplaceCapability
{
    public const SEARCH_LISTINGS = 'search_listings';
    public const GET_LISTING = 'get_listing';
    public const GET_INVENTORY = 'get_inventory';
    public const IMPORT_ORDERS = 'import_orders';
    public const UPDATE_LISTING = 'update_listing';
    public const UPDATE_PRICE = 'update_price';
    public const UPDATE_INVENTORY = 'update_inventory';
    public const PAUSE_LISTING = 'pause_listing';
    public const RESUME_LISTING = 'resume_listing';

    /** @return array<int,string> */
    public static function readOnly(): array
    {
        return [self::SEARCH_LISTINGS, self::GET_LISTING, self::GET_INVENTORY, self::IMPORT_ORDERS];
    }

    /** @return array<int,string> */
    public static function writeOnly(): array
    {
        return [self::UPDATE_LISTING, self::UPDATE_PRICE, self::UPDATE_INVENTORY, self::PAUSE_LISTING, self::RESUME_LISTING];
    }

    public static function isAllowed(string $capability): bool
    {
        return in_array($capability, self::readOnly(), true);
    }

    public static function isWriteAllowed(string $capability): bool
    {
        return in_array($capability, self::writeOnly(), true);
    }

    /** @return array<int,string> */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(self::readOnly(), self::writeOnly())));
    }
}
