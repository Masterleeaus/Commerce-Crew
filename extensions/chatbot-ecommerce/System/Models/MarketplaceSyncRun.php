<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


final class MarketplaceSyncRun extends Model
{
    protected $table = 'ext_chatbot_marketplace_sync_runs';
    protected $guarded = [];
    protected $hidden = ['credential_reference'];
    protected $casts = ['cursor_before' => 'array', 'cursor_after' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
