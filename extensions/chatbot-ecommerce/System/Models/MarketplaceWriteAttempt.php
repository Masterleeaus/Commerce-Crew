<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class MarketplaceWriteAttempt extends Model
{
    protected $table = 'ext_chatbot_marketplace_write_attempts';
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(MarketplaceWriteProposal::class, 'proposal_id');
    }
}
