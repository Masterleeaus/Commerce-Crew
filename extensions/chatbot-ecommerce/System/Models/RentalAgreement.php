<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RentalAgreement extends Model
{
    protected $table = 'ext_chatbot_rental_agreements';
    protected $guarded = [];
    protected $casts = [
        'starts_on' => 'date', 'ends_on' => 'date', 'next_charge_date' => 'date',
        'allow_partial_payments' => 'boolean', 'auto_allocate_payments' => 'boolean',
        'metadata' => 'array', 'activated_at' => 'datetime', 'ended_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function account(): BelongsTo { return $this->belongsTo(RentalAccount::class, 'rental_account_id'); }
    public function rates(): HasMany { return $this->hasMany(RentalAgreementRate::class, 'rental_agreement_id')->orderBy('effective_from'); }
    public function charges(): HasMany { return $this->hasMany(RentalCharge::class, 'rental_agreement_id')->orderBy('period_start'); }
}
