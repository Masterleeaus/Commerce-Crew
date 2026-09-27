<?php

namespace App\Extensions\Chatbot\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotParticipant extends Model
{
    protected $table = 'ext_chatbot_participants';

    protected $fillable = [
        'conversation_id', 'type', 'reference', 'display_name', 'metadata',
        'last_seen_at', 'last_read_history_id', 'last_read_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_seen_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'conversation_id');
    }
}
