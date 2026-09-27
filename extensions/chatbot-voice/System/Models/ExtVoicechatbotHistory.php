<?php

namespace App\Extensions\ChatbotVoice\System\Models;

use App\Extensions\ChatbotVoice\System\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtVoicechatbotHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'message',
    ];

    public $casts = [
        'role' => RoleEnum::class,
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ExtVoicechabotConversation::class, 'conversation_id');
    }
}
