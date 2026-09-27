<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CommerceCommunicationPolicy extends Model
{
    protected $table = 'ext_chatbot_commerce_communication_policies';
    protected $guarded = [];
    protected $casts = [
        'auto_reply_enabled' => 'boolean', 'auto_reply_confidence' => 'float',
        'automatic_refund_limit' => 'integer', 'automatic_discount_limit' => 'integer',
        'automatic_store_credit_limit' => 'integer', 'automatic_replacement_limit' => 'integer',
        'automatic_cancellation_limit' => 'integer', 'require_identity_for_order_data' => 'boolean',
        'human_handoff' => 'boolean', 'return_window_days' => 'integer',
        'allowed_auto_actions' => 'array', 'escalation_triggers' => 'array',
        'enabled_channels' => 'array', 'specialist_product_ids' => 'array',
        'active' => 'boolean', 'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
