<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ListingComplianceRule extends Model
{
    protected $table = 'ext_chatbot_listing_compliance_rules';
    protected $guarded = [];
    protected $casts = ['terms'=>'array','field_scopes'=>'array','metadata'=>'array','active'=>'boolean'];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
}
