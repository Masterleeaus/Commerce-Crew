<?php

namespace App\Extensions\ChatbotVoice\System\Enums;

use App\Enums\Traits\EnumTo;

enum RoleEnum: string
{
    use EnumTo;

    case user = 'user';
    case agent = 'agent';
}
