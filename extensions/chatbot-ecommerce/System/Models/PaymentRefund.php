<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentRefund extends Model
{
    protected $table = 'ext_chatbot_payment_refunds';
    protected $guarded = [];
    protected $casts = ['amount' => 'integer', 'metadata' => 'array', 'processed_at' => 'datetime', 'failed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
