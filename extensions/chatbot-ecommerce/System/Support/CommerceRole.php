<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CommerceRole
{
    public const SHOPPING_ASSISTANT = 'shopping_assistant';
    public const SELLER_STEWARD = 'seller_steward';
    public const CUSTOMER_COMMUNICATIONS = 'customer_communications';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [self::SHOPPING_ASSISTANT, self::SELLER_STEWARD, self::CUSTOMER_COMMUNICATIONS];
    }

    public static function valid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}
