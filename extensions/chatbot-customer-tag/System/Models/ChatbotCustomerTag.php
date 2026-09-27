<?php

namespace App\Extensions\ChatbotCustomerTag\System\Models;

use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChatbotCustomerTag extends Model
{
    protected $table = 'ext_chatbot_customer_tags';

    protected $fillable = [
        'tag',
        'tag_color',
        'background_color',
    ];

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            ChatbotConversation::class,
            'ext_chatbot_conversation_customer_tag',
            'customer_tag_id',
            'conversation_id'
        )->withTimestamps();
    }
}
