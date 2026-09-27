<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


final class MarketplaceConnection extends Model
{
    protected $table = 'ext_chatbot_marketplace_connections';
    protected $guarded = [];
    protected $hidden = ['credential_reference'];
    protected $casts = ['configuration' => 'encrypted:array', 'capabilities' => 'array', 'active' => 'boolean', 'write_enabled' => 'boolean', 'last_verified_at' => 'datetime', 'last_read_at' => 'datetime', 'last_write_at' => 'datetime', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
