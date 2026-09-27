<?php

namespace App\Extensions\Chatbot\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatbotAttachment extends Model
{
    use SoftDeletes;

    protected $table = 'ext_chatbot_attachments';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'quarantined_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'conversation_id');
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(ChatbotHistory::class, 'history_id');
    }
}
