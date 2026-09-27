<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ProductContentProfile extends Model
{
    protected $table = 'ext_chatbot_product_content_profiles';
    protected $guarded = [];
    protected $casts = [
        'features'=>'array','attributes'=>'array','materials'=>'array','dimensions'=>'array','compatibility'=>'array',
        'intended_users'=>'array','contraindications'=>'array','claims'=>'array','evidence_refs'=>'array','semantic_tags'=>'array','metadata'=>'array',
    ];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
}
