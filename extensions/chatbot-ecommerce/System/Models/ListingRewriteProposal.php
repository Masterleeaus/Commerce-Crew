<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ListingRewriteProposal extends Model
{
    protected $table = 'ext_chatbot_listing_rewrite_proposals';
    protected $guarded = [];
    protected $casts = ['before_content'=>'array','proposed_content'=>'array','compliance_summary'=>'array','metadata'=>'array','prepared_at'=>'datetime'];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
}
