<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOperation extends Model
{
    protected $table = 'ext_chatbot_payment_operations';
    protected $guarded = [];
    protected $casts = ['amount' => 'integer', 'response' => 'array', 'metadata' => 'array'];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
