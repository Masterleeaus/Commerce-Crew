<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class CommerceEscalation extends Model
{
    protected $table = 'ext_chatbot_commerce_escalations';
    protected $guarded = [];
    protected $casts = [
        'context_snapshot' => 'array', 'metadata' => 'array',
        'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function thread(): BelongsTo { return $this->belongsTo(CommerceCommunicationThread::class, 'thread_id'); }
    public function message(): BelongsTo { return $this->belongsTo(CommerceCommunicationMessage::class, 'message_id'); }
}
