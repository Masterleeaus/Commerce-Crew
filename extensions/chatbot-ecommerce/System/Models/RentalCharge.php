<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RentalCharge extends Model
{
    protected $table = 'ext_chatbot_rental_charges';
    protected $guarded = [];
    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date',
        'metadata' => 'array', 'paid_at' => 'datetime', 'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function account(): BelongsTo { return $this->belongsTo(RentalAccount::class, 'rental_account_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(RentalAgreement::class, 'rental_agreement_id'); }
    public function allocations(): HasMany { return $this->hasMany(RentalPaymentAllocation::class, 'rental_charge_id'); }
    public function adjustments(): HasMany { return $this->hasMany(RentalAdjustment::class, 'rental_charge_id'); }
}
