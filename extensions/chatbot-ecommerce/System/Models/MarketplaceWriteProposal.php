<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class MarketplaceWriteProposal extends Model
{
    protected $table = 'ext_chatbot_marketplace_write_proposals';
    protected $guarded = [];
    protected $hidden = ['approval_token_hash'];
    protected $casts = [
        'requested_changes' => 'array',
        'before_state' => 'array',
        'after_state' => 'array',
        'rollback_state' => 'array',
        'conflict_state' => 'array',
        'provider_result' => 'array',
        'approval_snapshot' => 'array',
        'metadata' => 'array',
        'approval_expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'queued_at' => 'datetime',
        'executed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MarketplaceConnection::class, 'connection_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(MarketplaceWriteAttempt::class, 'proposal_id');
    }
}
