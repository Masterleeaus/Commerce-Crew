<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentItem extends Model
{
    protected $table = 'ext_chatbot_fulfillment_items';
    protected $guarded = [];
    protected $casts = ['quantity' => 'integer', 'metadata' => 'array'];

    public function fulfillment(): BelongsTo { return $this->belongsTo(Fulfillment::class, 'fulfillment_id'); }
    public function cartLine(): BelongsTo { return $this->belongsTo(ChatbotCartLine::class, 'cart_line_id'); }
}
