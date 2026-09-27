<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class MarketplaceInventoryConflictEvent extends Model
{
    protected $table = 'ext_chatbot_marketplace_inventory_conflict_events';
    protected $guarded = [];
    protected $casts = ['payload'=>'array','occurred_at'=>'datetime'];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
    public function conflict(): BelongsTo { return $this->belongsTo(MarketplaceInventoryConflict::class, 'conflict_id'); }
}
