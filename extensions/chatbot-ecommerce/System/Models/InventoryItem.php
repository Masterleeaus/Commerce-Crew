<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use App\Extensions\ChatbotEcommerce\System\Support\InventoryAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $table = 'ext_chatbot_inventory';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'quantity' => 'integer',
        'reserved' => 'integer',
        'committed' => 'integer',
        'incoming' => 'integer',
        'damaged' => 'integer',
        'safety_stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'version' => 'integer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
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
