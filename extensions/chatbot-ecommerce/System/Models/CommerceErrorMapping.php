<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CommerceErrorMapping extends Model
{
    protected $table = 'ext_chatbot_commerce_error_lexicon';
    protected $guarded = [];
    protected $casts = ['retryable' => 'boolean', 'active' => 'boolean', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(static fn (self $mapping) => $mapping->uuid ??= (string) Str::uuid());
    }
}
