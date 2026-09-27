<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RentalAgreementRate extends Model
{
    protected $table = 'ext_chatbot_rental_agreement_rates';
    protected $guarded = [];
    protected $casts = ['effective_from' => 'date', 'effective_to' => 'date', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function agreement(): BelongsTo { return $this->belongsTo(RentalAgreement::class, 'rental_agreement_id'); }
}
