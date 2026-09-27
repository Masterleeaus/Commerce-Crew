<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    protected $table = 'ext_chatbot_product_variants';

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'dimensions' => 'array',
        'metadata' => 'array',
        'active' => 'boolean',
        'allow_backorder' => 'boolean',
        'price' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(InventoryItem::class, 'variant_id');
    }

    public function locationStock(): HasMany
    {
        return $this->hasMany(InventoryLocationStock::class, 'variant_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'variant_id');
    }
}
