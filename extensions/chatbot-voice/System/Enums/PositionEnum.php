<?php

namespace App\Extensions\ChatbotVoice\System\Enums;

use App\Enums\Traits\EnumTo;

enum PositionEnum: string
{
    use EnumTo;

    case left = 'left';
    case right = 'right';
}
