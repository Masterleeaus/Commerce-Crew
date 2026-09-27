<?php

namespace App\Extensions\Chatbot\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotHistory extends Model
{
    public $timestamps = false;

    protected $table = 'ext_chatbot_histories';

    protected $fillable = [
        'user_id',
        'chatbot_id',
        'conversation_id',
        'message_id',
        'model',
        'role',
        'message',
        'type',
        'media_url',
        'media_name',
        'message_type',
        'is_internal_note',
        'content_type',
        'read_at',
        'voice_call_duration',
        'created_at',
        'delivery_status', 'provider_message_id', 'failure_reason', 'retry_count', 'delivered_at', 'draft_status', 'structured_type', 'metadata',
        'idempotency_key', 'sent_at', 'failed_at',
    ];

    protected $casts = [
        'created_at'       => 'datetime',
        'is_internal_note' => 'boolean',
        'delivered_at' => 'datetime', 'read_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'conversation_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
