<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RentalAdjustment extends Model
{
    protected $table = 'ext_chatbot_rental_adjustments';
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'applied_at' => 'datetime', 'reversed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function account(): BelongsTo { return $this->belongsTo(RentalAccount::class, 'rental_account_id'); }
    public function charge(): BelongsTo { return $this->belongsTo(RentalCharge::class, 'rental_charge_id'); }
}
