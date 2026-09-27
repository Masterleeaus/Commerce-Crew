<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CheckoutSession extends Model
{
    protected $table = 'ext_chatbot_checkout_sessions';

    protected $guarded = [];

    protected $hidden = ['active_key', 'approval_token_hash'];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'delivery_option' => 'array',
        'consent' => 'array',
        'inventory_snapshot' => 'array',
        'pricing_snapshot' => 'array',
        'tax_breakdown' => 'array',
        'metadata' => 'array',
        'cart_version' => 'integer',
        'subtotal' => 'integer',
        'discount_total' => 'integer',
        'tax_total' => 'integer',
        'tax_zone_id' => 'integer',
        'shipping_total' => 'integer',
        'total' => 'integer',
        'expires_at' => 'datetime',
        'approval_expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'payment_pending_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $checkout): void {
            $checkout->uuid ??= (string) Str::uuid();
        });
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ChatbotCart::class, 'cart_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(CheckoutOperation::class, 'checkout_session_id');
    }

    public function shippingQuote(): BelongsTo
    {
        return $this->belongsTo(ShippingQuote::class, 'shipping_quote_id');
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class, 'checkout_session_id');
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
