<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class OrderWorkbenchAction extends Model
{
    protected $table = 'ext_chatbot_order_workbench_actions';
    protected $guarded = [];
    protected $casts = [
        'amount' => 'integer', 'proposal' => 'array', 'result' => 'array',
        'expires_at' => 'datetime', 'executed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function order(): BelongsTo { return $this->belongsTo(UnifiedCommerceOrder::class, 'unified_order_id'); }
    public function exception(): BelongsTo { return $this->belongsTo(OrderException::class, 'exception_id'); }
    public function communicationThread(): BelongsTo { return $this->belongsTo(CommerceCommunicationThread::class, 'communication_thread_id'); }
}
