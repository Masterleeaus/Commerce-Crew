<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


final class MarketplaceOrderLineSnapshot extends Model
{
    protected $table = 'ext_chatbot_marketplace_order_line_snapshots';
    protected $guarded = [];
    protected $hidden = ['credential_reference'];
    protected $casts = ['snapshot' => 'array'];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
