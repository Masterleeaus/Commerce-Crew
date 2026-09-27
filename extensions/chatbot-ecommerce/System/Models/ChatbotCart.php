<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatbotCart extends Model
{
    use HasFactory;

    protected $table = 'ext_chatbot_carts';

    protected $fillable = [
        'uuid',
        'chatbot_customer_id',
        'chatbot_id',
        'session_id',
        'product_source',
        'product_data',
        'products',
        'status',
        'currency',
        'lines',
        'coupon_code',
        'list_subtotal',
        'subtotal',
        'item_discount_total',
        'discount_total',
        'tax_total',
        'tax_zone_id',
        'tax_context_hash',
        'tax_breakdown',
        'shipping_subtotal',
        'shipping_discount_total',
        'shipping_total',
        'total',
        'price_snapshot',
        'pricing_snapshot_uuid',
        'pricing_snapshot_hash',
        'calculation_version',
        'expires_at',
        'metadata',
        'active_key',
        'conversation_id',
        'channel',
        'customer_identity_id',
        'merged_into_cart_id',
        'recovery_token_hash',
        'line_count',
        'version',
        'last_activity_at',
        'abandoned_at',
        'recovered_at',
        'converted_at',
    ];

    protected $hidden = [
        'active_key',
        'recovery_token_hash',
    ];

    protected $casts = [
        'products' => 'array',
        'product_data' => 'array',
        'lines' => 'array',
        'price_snapshot' => 'array',
        'metadata' => 'array',
        'tax_breakdown' => 'array',
        'expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'recovered_at' => 'datetime',
        'converted_at' => 'datetime',
        'list_subtotal' => 'integer',
        'subtotal' => 'integer',
        'item_discount_total' => 'integer',
        'discount_total' => 'integer',
        'tax_total' => 'integer',
        'tax_zone_id' => 'integer',
        'shipping_subtotal' => 'integer',
        'shipping_discount_total' => 'integer',
        'shipping_total' => 'integer',
        'total' => 'integer',
        'line_count' => 'integer',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cart): void {
            $cart->uuid ??= (string) Str::uuid();
            $cart->last_activity_at ??= now();
        });
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'cart_id');
    }

    public function cartLines(): HasMany
    {
        return $this->hasMany(ChatbotCartLine::class, 'cart_id')->orderBy('id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(CartOperation::class, 'cart_id');
    }

    public function pricingSnapshots(): HasMany
    {
        return $this->hasMany(PricingSnapshot::class, 'cart_id')->orderByDesc('cart_version');
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'cart_id');
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class, 'cart_id')->orderByDesc('id');
    }

    public function shippingQuotes(): HasMany
    {
        return $this->hasMany(ShippingQuote::class, 'cart_id')->orderByDesc('id');
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class, 'cart_id')->orderByDesc('id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_cart_id');
    }
}
