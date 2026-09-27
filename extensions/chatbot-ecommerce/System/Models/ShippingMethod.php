<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShippingMethod extends Model
{
    protected $table = 'ext_chatbot_shipping_methods';
    protected $guarded = [];
    protected $casts = [
        'active' => 'boolean', 'configuration' => 'array', 'metadata' => 'array',
        'amount' => 'integer', 'base_amount' => 'integer', 'per_kg_amount' => 'integer',
        'free_above' => 'integer', 'minimum_subtotal' => 'integer', 'maximum_subtotal' => 'integer',
        'minimum_weight_grams' => 'integer', 'maximum_weight_grams' => 'integer', 'priority' => 'integer',
        'estimated_days_min' => 'integer', 'estimated_days_max' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $method) => $method->uuid ??= (string) Str::uuid());
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
