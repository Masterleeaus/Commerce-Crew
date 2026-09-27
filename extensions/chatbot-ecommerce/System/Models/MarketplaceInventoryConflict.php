<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class MarketplaceInventoryConflict extends Model
{
    protected $table = 'ext_chatbot_marketplace_inventory_conflicts';
    protected $guarded = [];
    protected $casts = [
        'canonical_quantity'=>'integer','target_quantity'=>'integer','external_quantity'=>'integer','delta'=>'integer',
        'oversell_exposure'=>'integer','canonical_version'=>'integer','observed_at'=>'datetime','first_detected_at'=>'datetime',
        'last_detected_at'=>'datetime','acknowledged_at'=>'datetime','ignored_at'=>'datetime','ignored_until'=>'datetime',
        'resolved_at'=>'datetime','assessment'=>'array','metadata'=>'array',
    ];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
    public function mapping(): BelongsTo { return $this->belongsTo(MarketplaceInventoryMapping::class, 'mapping_id'); }
    public function connection(): BelongsTo { return $this->belongsTo(MarketplaceConnection::class, 'connection_id'); }
    public function scanRun(): BelongsTo { return $this->belongsTo(MarketplaceInventoryScanRun::class, 'scan_run_id'); }
    public function writeProposal(): BelongsTo { return $this->belongsTo(MarketplaceWriteProposal::class, 'write_proposal_id'); }
    public function events(): HasMany { return $this->hasMany(MarketplaceInventoryConflictEvent::class, 'conflict_id')->orderBy('id'); }
}
