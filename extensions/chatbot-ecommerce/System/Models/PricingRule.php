<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PricingRule extends Model
{
    protected $table = 'ext_chatbot_pricing_rules';
    protected $guarded = [];
    protected $casts = [
        'stackable' => 'boolean',
        'active' => 'boolean',
        'value' => 'integer',
        'min_quantity' => 'integer',
        'min_subtotal' => 'integer',
        'conditions' => 'array',
        'metadata' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $rule): void {
            $rule->uuid ??= (string) Str::uuid();
        });
    }
}
