<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CommerceSpendLimit extends Model
{
    protected $table = 'ext_chatbot_commerce_spend_limits';
    protected $guarded = [];
    protected $casts = [
        'max_order_value' => 'integer',
        'daily_spend_limit' => 'integer',
        'spent_today' => 'integer',
        'period_date' => 'date',
        'enabled' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $limit) => $limit->uuid ??= (string) Str::uuid());
    }
}
