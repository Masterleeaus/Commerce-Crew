<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaxExemption extends Model
{
    protected $table = 'ext_chatbot_tax_exemptions';
    protected $guarded = [];
    protected $casts = [
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $exemption): void {
            $exemption->uuid ??= (string) Str::uuid();
            $exemption->exemption_key ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
