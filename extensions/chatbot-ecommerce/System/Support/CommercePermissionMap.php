<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CommercePermissionMap
{
    public static function forRoute(?string $routeName): string
    {
        $name = strtolower((string) $routeName);
        $rules = [
            'order-workbench' => 'chatbot-ecommerce.manage-order-workbench',
            'marketplace-bulk' => 'chatbot-ecommerce.execute-marketplace-bulk-writes',
            'marketplace-write' => 'chatbot-ecommerce.execute-marketplace-writes',
            'listing-intelligence' => 'chatbot-ecommerce.manage-listing-compliance',
            'marketplace-inventory' => 'chatbot-ecommerce.resolve-marketplace-inventory-conflicts',
            'marketplaces' => 'chatbot-ecommerce.manage-marketplace-connections',
            'payments' => 'chatbot-ecommerce.manage-payments',
            'bnpl' => 'chatbot-ecommerce.manage-bnpl',
            'rental-hire' => 'chatbot-ecommerce.manage-rental-hire',
            'pricing' => 'chatbot-ecommerce.manage-pricing',
            'tax' => 'chatbot-ecommerce.manage-pricing',
            'shipping' => 'chatbot-ecommerce.manage-checkout',
            'fulfillments' => 'chatbot-ecommerce.manage-orders',
            'inventory' => 'chatbot-ecommerce.manage-inventory',
            'support' => 'chatbot-ecommerce.manage-customer-communications',
            'orders' => 'chatbot-ecommerce.manage-orders',
            'commerce' => 'chatbot-ecommerce.manage-budget-locks',
            'credentials' => 'chatbot-ecommerce.manage-credentials',
        ];
        foreach ($rules as $fragment => $permission) {
            if (str_contains($name, $fragment)) {
                return $permission;
            }
        }

        return 'chatbot-ecommerce.read';
    }
}
