<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class CommerceOrder extends Model
{
    protected $table = 'ext_chatbot_commerce_orders';
    protected $guarded = [];
    protected $casts = [
        'subtotal' => 'integer', 'discount_total' => 'integer', 'tax_total' => 'integer',
        'shipping_total' => 'integer', 'total' => 'integer', 'refunded_total' => 'integer',
        'customer_snapshot' => 'array', 'billing_address' => 'array', 'shipping_address' => 'array',
        'pricing_snapshot' => 'array', 'tax_breakdown' => 'array', 'payment_snapshot' => 'array',
        'metadata' => 'array', 'placed_at' => 'datetime', 'confirmed_at' => 'datetime',
        'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $order) => $order->uuid ??= (string) Str::uuid());
    }

    public function items(): HasMany { return $this->hasMany(CommerceOrderItem::class, 'order_id'); }
    public function events(): HasMany { return $this->hasMany(CommerceOrderEvent::class, 'order_id')->orderBy('id'); }
    public function returns(): HasMany { return $this->hasMany(CommerceReturn::class, 'order_id')->orderByDesc('id'); }
    public function checkout(): BelongsTo { return $this->belongsTo(CheckoutSession::class, 'checkout_session_id'); }
    public function paymentIntent(): BelongsTo { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
}
