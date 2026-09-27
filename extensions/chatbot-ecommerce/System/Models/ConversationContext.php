<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ConversationContext extends Model
{
    protected $table = 'ext_chatbot_commerce_contexts';
    protected $guarded = [];
    protected $casts = [
        'current_product_ids' => 'array',
        'last_filters' => 'array',
        'short_term_preferences' => 'array',
        'pending_actions' => 'array',
        'context_version' => 'integer',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $context): void {
            $context->uuid ??= (string) Str::uuid();
            $context->last_accessed_at ??= now();
        });
    }
}
