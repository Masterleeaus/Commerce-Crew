<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RentalAccount extends Model
{
    protected $table = 'ext_chatbot_rental_accounts';
    protected $guarded = [];
    protected $hidden = ['access_token_hash'];
    protected $casts = ['metadata' => 'array', 'closed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function agreements(): HasMany { return $this->hasMany(RentalAgreement::class, 'rental_account_id'); }
    public function charges(): HasMany { return $this->hasMany(RentalCharge::class, 'rental_account_id'); }
    public function payments(): HasMany { return $this->hasMany(RentalPayment::class, 'rental_account_id'); }
    public function adjustments(): HasMany { return $this->hasMany(RentalAdjustment::class, 'rental_account_id'); }
    public function receipts(): HasMany { return $this->hasMany(RentalReceipt::class, 'rental_account_id'); }
    public function ledgerEntries(): HasMany { return $this->hasMany(RentalLedgerEntry::class, 'rental_account_id'); }
}
