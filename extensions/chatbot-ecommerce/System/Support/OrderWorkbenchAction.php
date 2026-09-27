<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class OrderWorkbenchAction
{
    private const SUPPORTED = [
        'acknowledge', 'assign', 'resolve', 'refund_proposal', 'prepare_customer_contact', 'link_communication_thread',
    ];

    public static function isSupported(string $action): bool
    {
        return in_array($action, self::SUPPORTED, true);
    }

    public static function requiresApproval(string $action): bool
    {
        return in_array($action, ['refund_proposal'], true);
    }
}
