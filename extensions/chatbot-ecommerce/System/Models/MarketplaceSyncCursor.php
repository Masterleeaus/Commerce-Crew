<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


final class MarketplaceSyncCursor extends Model
{
    protected $table = 'ext_chatbot_marketplace_sync_cursors';
    protected $guarded = [];
    protected $hidden = ['credential_reference'];
    protected $casts = ['cursor' => 'array', 'last_synced_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
