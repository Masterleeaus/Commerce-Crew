<?php

namespace App\Extensions\ChatbotVoice\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtVoicechatbotTrain extends Model
{
    use HasFactory;

    protected $fillable = [
        'chatbot_id',
        'user_id',

        'doc_id',
        'name',

        'type',
        'file',
        'url',
        'text',
        'trained_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
