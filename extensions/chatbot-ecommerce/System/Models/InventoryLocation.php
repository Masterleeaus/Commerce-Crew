<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    protected $table = 'ext_chatbot_inventory_locations';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'priority' => 'integer',
        'metadata' => 'array',
    ];

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryLocationStock::class, 'location_id');
    }
}
