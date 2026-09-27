<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TaxRate extends Model
{
    protected $table = 'ext_chatbot_tax_rates';
    protected $guarded = [];
    protected $casts = [
        'active' => 'boolean',
        'compound' => 'boolean',
        'applies_to_products' => 'boolean',
        'applies_to_shipping' => 'boolean',
        'rate_bps' => 'integer',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $rate) => $rate->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'tax_zone_id');
    }
}
