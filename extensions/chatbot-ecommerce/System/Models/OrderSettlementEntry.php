<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class OrderSettlementEntry extends Model
{
    protected $table = 'ext_chatbot_order_settlement_entries';
    protected $guarded = [];
    protected $casts = ['amount' => 'integer', 'source_snapshot' => 'array', 'occurred_at' => 'datetime', 'imported_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
        static::updating(static fn () => throw new LogicException('Settlement source entries are immutable.'));
        static::deleting(static fn () => throw new LogicException('Settlement source entries are immutable.'));
    }

    public function order(): BelongsTo { return $this->belongsTo(UnifiedCommerceOrder::class, 'unified_order_id'); }
}
