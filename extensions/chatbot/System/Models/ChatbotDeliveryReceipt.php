<?php

namespace App\Extensions\Chatbot\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotDeliveryReceipt extends Model
{
    protected $table = 'ext_chatbot_delivery_receipts';

    protected $fillable = ['history_id', 'provider', 'provider_message_id', 'status', 'payload', 'occurred_at'];

    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatbotHistory::class, 'history_id');
    }
}
