<?php

namespace App\Extensions\ChatbotVoice\System\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtVoicechabotConversation extends Model
{
    use HasFactory;

    protected $fillable = ['chatbot_uuid', 'conversation_id'];

    public function chat_histories(): HasMany
    {
        return $this->hasMany(ExtVoicechatbotHistory::class, 'conversation_id');
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(ExtVoiceChatbot::class, 'chatbot_uuid', 'uuid');
    }
}
