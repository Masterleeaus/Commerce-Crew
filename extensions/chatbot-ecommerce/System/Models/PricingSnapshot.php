<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingSnapshot extends Model
{
    protected $table = 'ext_chatbot_pricing_snapshots';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'cart_version' => 'integer',
        'subtotal' => 'integer',
        'discount_total' => 'integer',
        'shipping_total' => 'integer',
        'tax_total' => 'integer',
        'total' => 'integer',
        'calculation' => 'array',
        'created_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ChatbotCart::class, 'cart_id');
    }
}
