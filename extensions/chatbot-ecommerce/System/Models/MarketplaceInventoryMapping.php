<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class MarketplaceInventoryMapping extends Model
{
    protected $table = 'ext_chatbot_marketplace_inventory_mappings';
    protected $guarded = [];
    protected $casts = [
        'allocation_bps'=>'integer','buffer_quantity'=>'integer','minimum_quantity'=>'integer','maximum_quantity'=>'integer',
        'active'=>'boolean','verified'=>'boolean','last_external_quantity'=>'integer','last_target_quantity'=>'integer',
        'last_scanned_at'=>'datetime','last_in_sync_at'=>'datetime','metadata'=>'array',
    ];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
    public function connection(): BelongsTo { return $this->belongsTo(MarketplaceConnection::class, 'connection_id'); }
    public function listing(): BelongsTo { return $this->belongsTo(MarketplaceListingSnapshot::class, 'listing_snapshot_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function location(): BelongsTo { return $this->belongsTo(InventoryLocation::class, 'location_id'); }
}
