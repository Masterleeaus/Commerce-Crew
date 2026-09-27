<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class MarketplaceBulkBatch extends Model
{
    protected $table = 'ext_chatbot_marketplace_bulk_batches';
    protected $guarded = [];
    protected $hidden = ['approval_token_hash'];
    protected $casts = [
        'selection_filters' => 'array', 'change_template' => 'array', 'impact_summary' => 'array', 'metadata' => 'array',
        'approval_expires_at' => 'datetime', 'approved_at' => 'datetime', 'started_at' => 'datetime',
        'completed_at' => 'datetime', 'rolled_back_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function connection(): BelongsTo { return $this->belongsTo(MarketplaceConnection::class, 'connection_id'); }
    public function items(): HasMany { return $this->hasMany(MarketplaceBulkItem::class, 'batch_id'); }
}
