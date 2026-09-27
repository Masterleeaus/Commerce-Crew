<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MarketplaceOrderSnapshot extends Model
{
    protected $table = 'ext_chatbot_marketplace_order_snapshots';
    protected $guarded = [];
    protected $hidden = ['credential_reference'];
    protected $casts = ['snapshot' => 'array', 'placed_at' => 'datetime', 'provider_updated_at' => 'datetime', 'first_imported_at' => 'datetime', 'last_imported_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
    public function lines(): HasMany { return $this->hasMany(MarketplaceOrderLineSnapshot::class, 'order_snapshot_id')->orderBy('id'); }
}
