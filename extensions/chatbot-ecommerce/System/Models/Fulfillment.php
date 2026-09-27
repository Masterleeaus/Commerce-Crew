<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Fulfillment extends Model
{
    protected $table = 'ext_chatbot_fulfillments';
    protected $guarded = [];
    protected $casts = [
        'processing_at' => 'datetime', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime', 'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $fulfillment) => $fulfillment->uuid ??= (string) Str::uuid());
    }

    public function checkout(): BelongsTo { return $this->belongsTo(CheckoutSession::class, 'checkout_session_id'); }
    public function cart(): BelongsTo { return $this->belongsTo(ChatbotCart::class, 'cart_id'); }
    public function items(): HasMany { return $this->hasMany(FulfillmentItem::class, 'fulfillment_id')->orderBy('id'); }
}
