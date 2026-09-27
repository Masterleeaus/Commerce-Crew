<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class OrderReconciliation extends Model
{
    protected $table = 'ext_chatbot_order_reconciliations';
    protected $guarded = [];
    protected $casts = [
        'gross_amount' => 'integer', 'fee_amount' => 'integer', 'tax_withheld_amount' => 'integer',
        'shipping_cost_amount' => 'integer', 'refund_amount' => 'integer', 'chargeback_amount' => 'integer',
        'adjustment_amount' => 'integer', 'expected_net_amount' => 'integer', 'reported_net_amount' => 'integer',
        'variance_amount' => 'integer', 'evidence' => 'array', 'reconciled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
        static::updating(static fn () => throw new LogicException('Reconciliation history is immutable.'));
        static::deleting(static fn () => throw new LogicException('Reconciliation history is immutable.'));
    }

    public function order(): BelongsTo { return $this->belongsTo(UnifiedCommerceOrder::class, 'unified_order_id'); }
}
