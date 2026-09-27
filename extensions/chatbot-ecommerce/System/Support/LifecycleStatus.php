<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class LifecycleStatus
{
    public const ENABLED = 'enabled';
    public const DISABLED = 'disabled';
    public const UNINSTALLING = 'uninstalling';
    public const UNINSTALLED = 'uninstalled';

    public static function canServe(string $status): bool
    {
        return $status === self::ENABLED;
    }

    public static function preservesData(string $status): bool
    {
        return in_array($status, [self::DISABLED, self::UNINSTALLING, self::UNINSTALLED], true);
    }
}
