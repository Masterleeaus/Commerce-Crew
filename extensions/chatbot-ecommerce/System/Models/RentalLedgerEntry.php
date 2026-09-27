<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RentalLedgerEntry extends Model
{
    protected $table = 'ext_chatbot_rental_ledger_entries';
    protected $guarded = [];
    protected $casts = ['effective_at' => 'datetime', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function account(): BelongsTo { return $this->belongsTo(RentalAccount::class, 'rental_account_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(RentalAgreement::class, 'rental_agreement_id'); }
    public function charge(): BelongsTo { return $this->belongsTo(RentalCharge::class, 'rental_charge_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(RentalPayment::class, 'rental_payment_id'); }
    public function adjustment(): BelongsTo { return $this->belongsTo(RentalAdjustment::class, 'rental_adjustment_id'); }
}
