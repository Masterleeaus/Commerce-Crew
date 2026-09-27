<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BnplProviderProfile extends Model
{
    protected $table = 'ext_chatbot_bnpl_provider_profiles';
    protected $guarded = [];
    protected $hidden = ['credential_reference'];
    protected $casts = [
        'active' => 'boolean',
        'supported_scopes' => 'array',
        'supported_shopping_modes' => 'array',
        'supported_account_types' => 'array',
        'supported_currencies' => 'array',
        'supported_countries' => 'array',
        'minimum_amount' => 'integer',
        'maximum_amount' => 'integer',
        'installment_counts' => 'array',
        'interval_days' => 'integer',
        'first_payment_delay_days' => 'integer',
        'merchant_fee_bps' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function offers(): HasMany
    {
        return $this->hasMany(BnplOffer::class, 'bnpl_provider_profile_id');
    }

    /** @return array<string,mixed> */
    public function policy(): array
    {
        return [
            'active' => (bool) $this->active,
            'supported_scopes' => $this->supported_scopes ?? [],
            'supported_shopping_modes' => $this->supported_shopping_modes ?? [],
            'supported_account_types' => $this->supported_account_types ?? [],
            'supported_currencies' => $this->supported_currencies ?? [],
            'supported_countries' => $this->supported_countries ?? [],
            'minimum_amount' => (int) $this->minimum_amount,
            'maximum_amount' => $this->maximum_amount !== null ? (int) $this->maximum_amount : null,
            'installment_counts' => $this->installment_counts ?? [],
            'licence_reference' => $this->licence_reference,
            'terms_url' => $this->terms_url,
            'hardship_url' => $this->hardship_url,
            'complaints_url' => $this->complaints_url,
        ];
    }
}
