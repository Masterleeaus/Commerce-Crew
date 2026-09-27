<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class MarketplaceRateLimit extends Model
{
    protected $table = 'ext_chatbot_marketplace_rate_limits';
    protected $guarded = [];
    protected $casts = [
        'window_started_at' => 'datetime',
        'reset_at' => 'datetime',
        'blocked_until' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
