<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $table = 'ext_chatbot_coupons';
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'array',
        'target_ids' => 'array',
        'channels' => 'array',
        'active' => 'boolean',
        'first_order_only' => 'boolean',
        'stackable' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'value' => 'integer',
        'minimum_amount' => 'integer',
        'max_discount_amount' => 'integer',
        'usage_limit' => 'integer',
        'usage_limit_per_customer' => 'integer',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id');
    }
}
