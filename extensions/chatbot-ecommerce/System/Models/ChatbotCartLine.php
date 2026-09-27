<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotCartLine extends Model
{
    protected $table = 'ext_chatbot_cart_lines';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'list_unit_price' => 'integer',
        'unit_price' => 'integer',
        'gross_total' => 'integer',
        'discount_total' => 'integer',
        'net_before_tax' => 'integer',
        'tax_rate_bps' => 'integer',
        'tax_total' => 'integer',
        'line_total' => 'integer',
        'discounts' => 'array',
        'customisation' => 'array',
        'metadata' => 'array',
        'price_snapshot' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ChatbotCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
