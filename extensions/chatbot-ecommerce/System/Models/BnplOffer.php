<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BnplOffer extends Model
{
    protected $table = 'ext_chatbot_bnpl_offers';
    protected $guarded = [];
    protected $hidden = ['request_hash'];
    protected $casts = [
        'amount' => 'integer',
        'customer_fee' => 'integer',
        'merchant_fee' => 'integer',
        'total_payable' => 'integer',
        'installment_count' => 'integer',
        'interval_days' => 'integer',
        'first_installment_amount' => 'integer',
        'regular_installment_amount' => 'integer',
        'installment_schedule' => 'array',
        'eligibility_snapshot' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'selected_at' => 'datetime',
        'approved_at' => 'datetime',
        'captured_at' => 'datetime',
        'declined_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(BnplProviderProfile::class, 'bnpl_provider_profile_id');
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }

    public function rentalPayment(): BelongsTo
    {
        return $this->belongsTo(RentalPayment::class, 'rental_payment_id');
    }
}
