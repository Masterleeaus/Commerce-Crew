<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RentalReceipt extends Model
{
    protected $table = 'ext_chatbot_rental_receipts';
    protected $guarded = [];
    protected $casts = ['version' => 'integer', 'data' => 'array', 'metadata' => 'array', 'issued_at' => 'datetime', 'voided_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function account(): BelongsTo { return $this->belongsTo(RentalAccount::class, 'rental_account_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(RentalPayment::class, 'rental_payment_id'); }
}
