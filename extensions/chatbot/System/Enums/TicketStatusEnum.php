<?php

namespace App\Extensions\Chatbot\System\Enums;

use App\Enums\Traits\EnumTo;

enum TicketStatusEnum: string
{
    use EnumTo;

    case new = 'new';
    case closed = 'closed';
    case deleted = 'deleted';
}
