<?php

namespace App\Extensions\Chatbot\System\Enums;

use App\Enums\Traits\EnumTo;

enum HeaderBgEnum: string
{
    use EnumTo;

    case color = 'color';

    case gradient = 'gradient';

    case image = 'image';
}
