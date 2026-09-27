<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BrandVoiceProfile extends Model
{
    protected $table = 'ext_chatbot_brand_voice_profiles';
    protected $guarded = [];
    protected $casts = ['examples'=>'array','tone'=>'array','preferred_terms'=>'array','forbidden_terms'=>'array','guide'=>'array','active'=>'boolean'];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
}
