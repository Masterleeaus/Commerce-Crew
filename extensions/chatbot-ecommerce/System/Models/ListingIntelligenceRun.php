<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class ListingIntelligenceRun extends Model
{
    protected $table = 'ext_chatbot_listing_intelligence_runs';
    protected $guarded = [];
    protected $casts = ['source_content'=>'array','generated_content'=>'array','evidence_report'=>'array','compliance_summary'=>'array','metadata'=>'array','completed_at'=>'datetime'];
    protected static function booted(): void { static::creating(static fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
    public function findings(): HasMany { return $this->hasMany(ListingIntelligenceFinding::class, 'run_id'); }
    public function rewrites(): HasMany { return $this->hasMany(ListingRewriteProposal::class, 'run_id'); }
}
