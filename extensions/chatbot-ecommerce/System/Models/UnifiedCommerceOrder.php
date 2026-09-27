<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class UnifiedCommerceOrder extends Model
{
    protected $table = 'ext_chatbot_unified_orders';
    protected $guarded = [];
    protected $casts = [
        'external_authoritative' => 'boolean',
        'gross_total' => 'integer', 'discount_total' => 'integer', 'tax_total' => 'integer', 'shipping_total' => 'integer',
        'refund_total' => 'integer', 'chargeback_total' => 'integer', 'fee_total' => 'integer', 'shipping_cost_total' => 'integer',
        'expected_net_payout' => 'integer', 'reported_net_payout' => 'integer', 'settlement_variance' => 'integer',
        'shipping_address' => 'array', 'source_snapshot' => 'array', 'metadata' => 'array',
        'placed_at' => 'datetime', 'source_updated_at' => 'datetime', 'last_projected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function nativeOrder(): BelongsTo { return $this->belongsTo(CommerceOrder::class, 'native_order_id'); }
    public function marketplaceOrder(): BelongsTo { return $this->belongsTo(MarketplaceOrderSnapshot::class, 'marketplace_order_snapshot_id'); }
    public function sourceSnapshots(): HasMany { return $this->hasMany(UnifiedOrderSourceSnapshot::class, 'unified_order_id')->orderBy('sequence'); }
    public function settlements(): HasMany { return $this->hasMany(OrderSettlementEntry::class, 'unified_order_id')->orderBy('occurred_at'); }
    public function reconciliations(): HasMany { return $this->hasMany(OrderReconciliation::class, 'unified_order_id')->orderByDesc('id'); }
    public function exceptions(): HasMany { return $this->hasMany(OrderException::class, 'unified_order_id')->orderByDesc('last_detected_at'); }
    public function actions(): HasMany { return $this->hasMany(OrderWorkbenchAction::class, 'unified_order_id')->orderByDesc('id'); }
    public function communicationThreads(): HasMany { return $this->hasMany(CommerceCommunicationThread::class, 'unified_order_id')->orderByDesc('id'); }
}
