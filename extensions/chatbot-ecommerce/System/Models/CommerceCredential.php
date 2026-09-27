<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CommerceCredential extends Model
{
    protected $table = 'ext_chatbot_ecommerce_credentials';
    protected $guarded = [];
    protected $hidden = ['credentials', 'configuration'];
    protected $casts = [
        'credentials' => 'encrypted:array',
        'configuration' => 'encrypted:array',
        'version' => 'integer',
        'last_test_succeeded' => 'boolean',
        'last_tested_at' => 'datetime',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $credential) => $credential->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
