<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotWorkflowRun extends Model
{
    protected $table = 'ext_chatbot_workflow_runs';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'output' => 'array',
        'attempt' => 'integer',
        'max_attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'conversation_id');
    }
}
