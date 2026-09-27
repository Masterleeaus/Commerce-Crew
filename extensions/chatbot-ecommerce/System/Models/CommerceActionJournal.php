<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CommerceActionJournal extends Model
{
    protected $table = 'ext_chatbot_commerce_action_journal';
    protected $guarded = [];
    protected $casts = [
        'before_state' => 'array', 'after_state' => 'array', 'rollback_state' => 'array',
        'approval_snapshot' => 'array', 'metadata' => 'array', 'executed_at' => 'datetime', 'rolled_back_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $journal) => $journal->uuid ??= (string) Str::uuid());
    }
}
