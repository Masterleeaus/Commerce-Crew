<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MarketplaceSearch extends Model
{
    protected $table = 'ext_chatbot_marketplace_searches';
    protected $guarded = [];
    protected $hidden = ['credential_reference'];
    protected $casts = ['filters' => 'array', 'providers' => 'array', 'cache_hit' => 'boolean', 'errors' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'expires_at' => 'datetime', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
    public function results(): HasMany { return $this->hasMany(MarketplaceSearchResult::class, 'search_id')->orderBy('position'); }
}
