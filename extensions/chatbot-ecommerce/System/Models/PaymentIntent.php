<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class PaymentIntent extends Model
{
    protected $table = 'ext_chatbot_payment_intents';
    protected $guarded = [];
    protected $hidden = ['request_hash'];
    protected $casts = [
        'amount' => 'integer',
        'authorized_amount' => 'integer',
        'captured_amount' => 'integer',
        'refunded_amount' => 'integer',
        'instructions' => 'array',
        'provider_payload' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'authorized_at' => 'datetime',
        'captured_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }

    public function rentalPayment(): BelongsTo
    {
        return $this->belongsTo(RentalPayment::class, 'rental_payment_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(PaymentOperation::class, 'payment_intent_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class, 'payment_intent_id');
    }

    public function bnplOffer(): HasOne
    {
        return $this->hasOne(BnplOffer::class, 'payment_intent_id');
    }
}
