<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use App\Extensions\ChatbotEcommerce\System\Support\InventoryAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLocationStock extends Model
{
    protected $table = 'ext_chatbot_inventory_location_stock';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'reserved' => 'integer',
        'committed' => 'integer',
        'incoming' => 'integer',
        'damaged' => 'integer',
        'safety_stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'version' => 'integer',
        'metadata' => 'array',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function getAvailableAttribute(): int
    {
        return InventoryAvailability::calculate(
            (int) $this->quantity,
            (int) $this->reserved,
            (int) $this->committed,
            (int) $this->damaged,
            (int) $this->safety_stock,
        );
    }
}
