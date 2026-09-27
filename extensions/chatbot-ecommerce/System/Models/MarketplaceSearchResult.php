<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


final class MarketplaceSearchResult extends Model
{
    protected $table = 'ext_chatbot_marketplace_search_results';
    protected $guarded = [];
    protected $hidden = ['credential_reference'];
    protected $casts = ['attributes' => 'array', 'relevance_score' => 'float'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
