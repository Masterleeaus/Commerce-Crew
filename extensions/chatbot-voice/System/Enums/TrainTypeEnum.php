<?php

namespace App\Extensions\ChatbotVoice\System\Enums;

use App\Enums\Traits\EnumTo;

enum TrainTypeEnum: string
{
    use EnumTo;

    case url = 'url';
    case file = 'file';
    case text = 'text';

    public static function isInValid(string $step): bool
    {
        return ! self::isValid($step);
    }

    public static function isValid(string $step): bool
    {
        return in_array($step, self::toArray(), true);
    }
}
