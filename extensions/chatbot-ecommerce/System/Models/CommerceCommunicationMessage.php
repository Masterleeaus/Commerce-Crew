<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class CommerceCommunicationMessage extends Model
{
    protected $table = 'ext_chatbot_commerce_communication_messages';
    protected $guarded = [];
    protected $casts = [
        'confidence' => 'float', 'attachments' => 'array', 'metadata' => 'array',
        'received_at' => 'datetime', 'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function thread(): BelongsTo { return $this->belongsTo(CommerceCommunicationThread::class, 'thread_id'); }
    public function replyTo(): BelongsTo { return $this->belongsTo(self::class, 'reply_to_message_id'); }
}
