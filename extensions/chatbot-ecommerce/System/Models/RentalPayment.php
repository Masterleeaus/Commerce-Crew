<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class RentalPayment extends Model
{
    protected $table = 'ext_chatbot_rental_payments';
    protected $guarded = [];
    protected $casts = [
        'instructions' => 'array', 'metadata' => 'array', 'requested_at' => 'datetime',
        'received_at' => 'datetime', 'failed_at' => 'datetime', 'reversed_at' => 'datetime', 'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function account(): BelongsTo { return $this->belongsTo(RentalAccount::class, 'rental_account_id'); }
    public function allocations(): HasMany { return $this->hasMany(RentalPaymentAllocation::class, 'rental_payment_id'); }
    public function receipt(): HasOne { return $this->hasOne(RentalReceipt::class, 'rental_payment_id')->orderByDesc('version'); }
    public function paymentIntent(): BelongsTo { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
}
