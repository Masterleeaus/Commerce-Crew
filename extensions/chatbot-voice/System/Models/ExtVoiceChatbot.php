<?php

namespace App\Extensions\ChatbotVoice\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtVoiceChatbot extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'agent_id',
        'title',
        'bubble_message',
        'welcome_message',
        'instructions',
        'language',
        'ai_model',
        'avatar',
        'voice_id',
        'position',
        'active',
        'is_favorite',
    ];

    protected $casts = [
        'active' 		   => 'boolean',
        'is_favorite' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trains(): HasMany
    {
        return $this->hasMany(ExtVoicechatbotTrain::class, 'chatbot_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ExtVoicechabotConversation::class, 'chatbot_uuid', 'uuid');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
