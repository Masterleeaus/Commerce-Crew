<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ShippingZone extends Model
{
    protected $table = 'ext_chatbot_shipping_zones';
    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'countries' => 'array', 'regions' => 'array', 'postcode_patterns' => 'array', 'metadata' => 'array', 'priority' => 'integer'];

    protected static function booted(): void
    {
        static::creating(fn (self $zone) => $zone->uuid ??= (string) Str::uuid());
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class, 'shipping_zone_id')->orderByDesc('priority')->orderBy('id');
    }
}
