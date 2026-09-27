<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class OrderExceptionEvent extends Model
{
    protected $table = 'ext_chatbot_order_exception_events';
    protected $guarded = [];
    protected $casts = ['before_state' => 'array', 'after_state' => 'array', 'metadata' => 'array', 'occurred_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
        static::updating(static fn () => throw new LogicException('Order exception events are immutable.'));
        static::deleting(static fn () => throw new LogicException('Order exception events are immutable.'));
    }

    public function exception(): BelongsTo { return $this->belongsTo(OrderException::class, 'exception_id'); }
}
