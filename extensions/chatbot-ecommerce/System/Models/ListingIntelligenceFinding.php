<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ListingIntelligenceFinding extends Model
{
    protected $table = 'ext_chatbot_listing_intelligence_findings';
    protected $guarded = [];
    protected $casts = ['metadata'=>'array'];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
}
