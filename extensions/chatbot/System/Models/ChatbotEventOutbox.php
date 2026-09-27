<?php

namespace App\Extensions\Chatbot\System\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotEventOutbox extends Model
{
    protected $table = 'ext_chatbot_event_outbox';

    protected $fillable = [
        'event_uuid', 'event_type', 'aggregate_type', 'aggregate_id', 'payload',
        'status', 'attempts', 'available_at', 'published_at', 'last_error',
        'locked_at', 'locked_by', 'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
        'locked_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
