<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CommerceRoleRouter
{
    /** @param array<string,mixed> $context */
    public static function resolve(array $context): string
    {
        $actorType = strtolower(trim((string) ($context['actor_type'] ?? 'unknown')));
        $requestedRole = strtolower(trim((string) ($context['requested_role'] ?? '')));
        $authenticated = (bool) ($context['authenticated'] ?? false);

        if ($actorType === 'seller' && $authenticated) {
            if ($requestedRole === CommerceRole::CUSTOMER_COMMUNICATIONS) {
                return CommerceRole::CUSTOMER_COMMUNICATIONS;
            }

            return CommerceRole::SELLER_STEWARD;
        }

        if ($actorType !== 'customer') {
            return CommerceRole::SHOPPING_ASSISTANT;
        }

        if ($requestedRole === CommerceRole::CUSTOMER_COMMUNICATIONS) {
            return CommerceRole::CUSTOMER_COMMUNICATIONS;
        }

        $intent = strtolower(trim((string) ($context['intent'] ?? '')));
        $supportIntents = [
            'order_status', 'tracking', 'delivery_delay', 'return', 'exchange', 'refund_status',
            'damaged_item', 'missing_item', 'payment_problem', 'checkout_problem', 'warranty',
            'complaint', 'product_support', 'cancel_order', 'address_correction', 'human_support',
        ];

        $channel = strtolower(trim((string) ($context['channel'] ?? 'web')));
        $inboundSupport = (bool) ($context['inbound_support'] ?? false);
        $supportChannels = ['email', 'sms', 'whatsapp', 'telegram', 'messenger', 'instagram', 'marketplace_message', 'internal_inbox'];

        if (in_array($intent, $supportIntents, true) || ($inboundSupport && in_array($channel, $supportChannels, true))) {
            return CommerceRole::CUSTOMER_COMMUNICATIONS;
        }

        return CommerceRole::SHOPPING_ASSISTANT;
    }
}
