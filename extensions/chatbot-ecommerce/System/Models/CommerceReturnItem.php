<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class CommerceReturnItem extends Model
{
    protected $table = 'ext_chatbot_commerce_return_items';
    protected $guarded = [];
    protected $casts = ['quantity' => 'integer', 'amount' => 'integer', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(static fn (self $item) => $item->uuid ??= (string) Str::uuid());
    }

    public function returnRequest(): BelongsTo { return $this->belongsTo(CommerceReturn::class, 'return_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(CommerceOrderItem::class, 'order_item_id'); }
}
