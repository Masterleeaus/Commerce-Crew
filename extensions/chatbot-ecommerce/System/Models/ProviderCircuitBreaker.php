<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;

final class ProviderCircuitBreaker extends Model
{
    protected $table = 'ext_chatbot_provider_circuit_breakers';
    protected $guarded = [];
    protected $casts = [
        'failure_count' => 'integer',
        'opened_at' => 'datetime',
        'opened_until' => 'datetime',
        'last_failure_at' => 'datetime',
        'last_success_at' => 'datetime',
        'metadata' => 'array',
    ];
}
