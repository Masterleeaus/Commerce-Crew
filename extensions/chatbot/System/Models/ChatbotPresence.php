<?php

namespace App\Extensions\Chatbot\System\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotPresence extends Model
{
    protected $table = 'ext_chatbot_presence';

    protected $fillable = ['conversation_id', 'participant_key', 'state', 'last_seen_at', 'metadata'];

    protected $casts = ['last_seen_at' => 'datetime', 'metadata' => 'array'];
}
