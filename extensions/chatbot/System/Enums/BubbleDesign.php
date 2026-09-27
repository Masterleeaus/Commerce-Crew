<?php

namespace App\Extensions\Chatbot\System\Enums;

enum BubbleDesign: string
{
    case BLANK = 'blank';

    case PLAIN = 'plain';

    case LINKS = 'links';

    case MODERN = 'modern';

    case SUGGESTIONS = 'suggestions';

    case PROMO_BANNER = 'promo_banner';
}
