<?php

namespace App\Extensions\Chatbot\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotDraft extends Model
{
    protected $table = 'ext_chatbot_drafts';

    protected $fillable = ['conversation_id', 'user_id', 'content', 'attachments', 'metadata'];

    protected $casts = ['attachments' => 'array', 'metadata' => 'array'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'conversation_id');
    }
}
