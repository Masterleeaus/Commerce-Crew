<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class OrderException extends Model
{
    protected $table = 'ext_chatbot_order_exceptions';
    protected $guarded = [];
    protected $casts = [
        'evidence' => 'array', 'metadata' => 'array', 'first_detected_at' => 'datetime', 'last_detected_at' => 'datetime',
        'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function order(): BelongsTo { return $this->belongsTo(UnifiedCommerceOrder::class, 'unified_order_id'); }
    public function events(): HasMany { return $this->hasMany(OrderExceptionEvent::class, 'exception_id')->orderBy('id'); }
}
