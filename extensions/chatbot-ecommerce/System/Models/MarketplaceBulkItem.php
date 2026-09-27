<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class MarketplaceBulkItem extends Model
{
    protected $table = 'ext_chatbot_marketplace_bulk_items';
    protected $guarded = [];
    protected $casts = [
        'before_state' => 'array', 'proposed_changes' => 'array', 'impact' => 'array', 'conflicts' => 'array',
        'metadata' => 'array', 'executed_at' => 'datetime', 'rolled_back_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function batch(): BelongsTo { return $this->belongsTo(MarketplaceBulkBatch::class, 'batch_id'); }
    public function proposal(): BelongsTo { return $this->belongsTo(MarketplaceWriteProposal::class, 'proposal_id'); }
    public function listing(): BelongsTo { return $this->belongsTo(MarketplaceListingSnapshot::class, 'listing_snapshot_id'); }
}
