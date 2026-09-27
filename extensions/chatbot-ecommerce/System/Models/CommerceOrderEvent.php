<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class CommerceOrderEvent extends Model
{
    protected $table = 'ext_chatbot_commerce_order_events';
    protected $guarded = [];
    protected $casts = ['before_state' => 'array', 'after_state' => 'array', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(static fn (self $event) => $event->uuid ??= (string) Str::uuid());
    }

    public function order(): BelongsTo { return $this->belongsTo(CommerceOrder::class, 'order_id'); }
}
