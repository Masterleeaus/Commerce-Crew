<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'ext_chatbot_products';
    protected $guarded = [];
    protected $casts = [
        'active' => 'boolean', 'images' => 'array', 'tags' => 'array', 'metadata' => 'array',
        'published_at' => 'datetime', 'price' => 'integer', 'compare_at_price' => 'integer',
        'cost_price' => 'integer', 'recommendation_score' => 'decimal:4',
    ];
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
}
