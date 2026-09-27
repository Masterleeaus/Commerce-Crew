<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentWebhookEvent extends Model
{
    protected $table = 'ext_chatbot_payment_webhook_events';
    protected $guarded = [];
    protected $casts = [
        'signature_valid' => 'boolean',
        'attempt_count' => 'integer',
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'queued_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'dead_lettered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
