<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class UnifiedOrderSourceSnapshot extends Model
{
    protected $table = 'ext_chatbot_unified_order_source_snapshots';
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'captured_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
        static::updating(static fn () => throw new LogicException('Unified order source snapshots are immutable.'));
        static::deleting(static fn () => throw new LogicException('Unified order source snapshots are immutable.'));
    }

    public function order(): BelongsTo { return $this->belongsTo(UnifiedCommerceOrder::class, 'unified_order_id'); }
}
