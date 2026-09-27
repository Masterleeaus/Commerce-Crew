<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutOperation extends Model
{
    protected $table = 'ext_chatbot_checkout_operations';
    protected $guarded = [];
    protected $casts = ['response' => 'array', 'metadata' => 'array'];

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }
}
