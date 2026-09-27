<?php

namespace App\Extensions\Chatbot\System\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotStreamEvent extends Model
{
    protected $table = 'ext_chatbot_stream_events';

    protected $fillable = [
        'event_uuid', 'conversation_id', 'channel', 'event', 'payload', 'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];
}
