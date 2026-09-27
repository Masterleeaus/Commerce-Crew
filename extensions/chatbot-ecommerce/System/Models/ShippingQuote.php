<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShippingQuote extends Model
{
    protected $table = 'ext_chatbot_shipping_quotes';
    protected $guarded = [];
    protected $casts = [
        'amount' => 'integer', 'cart_version' => 'integer', 'subtotal' => 'integer', 'weight_grams' => 'integer',
        'expires_at' => 'datetime', 'selected_at' => 'datetime', 'expired_at' => 'datetime', 'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $quote) => $quote->uuid ??= (string) Str::uuid());
    }

    public function checkout(): BelongsTo { return $this->belongsTo(CheckoutSession::class, 'checkout_session_id'); }
    public function cart(): BelongsTo { return $this->belongsTo(ChatbotCart::class, 'cart_id'); }
    public function method(): BelongsTo { return $this->belongsTo(ShippingMethod::class, 'shipping_method_id'); }
}
