<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class CommerceReturn extends Model
{
    protected $table = 'ext_chatbot_commerce_returns';
    protected $guarded = [];
    protected $casts = [
        'requested_amount' => 'integer', 'refunded_amount' => 'integer', 'metadata' => 'array',
        'approved_at' => 'datetime', 'received_at' => 'datetime', 'resolved_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $return) => $return->uuid ??= (string) Str::uuid());
    }

    public function order(): BelongsTo { return $this->belongsTo(CommerceOrder::class, 'order_id'); }
    public function items(): HasMany { return $this->hasMany(CommerceReturnItem::class, 'return_id'); }
}
