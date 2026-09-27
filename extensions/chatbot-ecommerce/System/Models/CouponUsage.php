<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CouponUsage extends Model
{
    protected $table = 'ext_chatbot_coupon_usages';
    protected $guarded = [];
    protected $casts = [
        'reserved_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'released_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $usage): void {
            $usage->uuid ??= (string) Str::uuid();
        });
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ChatbotCart::class, 'cart_id');
    }
}
