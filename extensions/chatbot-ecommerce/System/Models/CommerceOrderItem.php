<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class CommerceOrderItem extends Model
{
    protected $table = 'ext_chatbot_commerce_order_items';
    protected $guarded = [];
    protected $casts = [
        'quantity' => 'integer', 'fulfilled_quantity' => 'integer', 'returned_quantity' => 'integer',
        'list_unit_price' => 'integer', 'unit_price' => 'integer', 'discount_total' => 'integer',
        'tax_total' => 'integer', 'line_total' => 'integer', 'product_snapshot' => 'array', 'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $item) => $item->uuid ??= (string) Str::uuid());
    }

    public function order(): BelongsTo { return $this->belongsTo(CommerceOrder::class, 'order_id'); }
}
