<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class MarketplaceInventoryScanRun extends Model
{
    protected $table = 'ext_chatbot_marketplace_inventory_scan_runs';
    protected $guarded = [];
    protected $casts = ['started_at'=>'datetime','completed_at'=>'datetime','metadata'=>'array'];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
    public function connection(): BelongsTo { return $this->belongsTo(MarketplaceConnection::class, 'connection_id'); }
    public function conflicts(): HasMany { return $this->hasMany(MarketplaceInventoryConflict::class, 'scan_run_id'); }
}
