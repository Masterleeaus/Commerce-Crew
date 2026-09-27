<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RentalPaymentAllocation extends Model
{
    protected $table = 'ext_chatbot_rental_payment_allocations';
    protected $guarded = [];
    protected $casts = ['allocated_at' => 'datetime', 'reversed_at' => 'datetime', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function payment(): BelongsTo { return $this->belongsTo(RentalPayment::class, 'rental_payment_id'); }
    public function charge(): BelongsTo { return $this->belongsTo(RentalCharge::class, 'rental_charge_id'); }
}
