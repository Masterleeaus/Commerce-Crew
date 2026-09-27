<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartOperation extends Model
{
    protected $table = 'ext_chatbot_cart_operations';

    protected $guarded = [];

    protected $casts = [
        'response' => 'array',
        'metadata' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ChatbotCart::class, 'cart_id');
    }
}
