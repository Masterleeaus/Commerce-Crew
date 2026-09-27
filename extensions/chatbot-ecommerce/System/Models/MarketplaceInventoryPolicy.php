<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class MarketplaceInventoryPolicy extends Model
{
    protected $table = 'ext_chatbot_marketplace_inventory_policies';
    protected $guarded = [];
    protected $casts = [
        'allocation_bps'=>'integer','buffer_quantity'=>'integer','minimum_quantity'=>'integer','maximum_quantity'=>'integer',
        'maximum_sync_age_seconds'=>'integer','quantity_tolerance'=>'integer','maximum_auto_delta'=>'integer',
        'require_fresh_snapshot'=>'boolean','auto_map_by_sku'=>'boolean','active'=>'boolean','metadata'=>'array',
    ];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
    public function connection(): BelongsTo { return $this->belongsTo(MarketplaceConnection::class, 'connection_id'); }
}
