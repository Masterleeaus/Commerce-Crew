<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;

final class CommerceLifecycleState extends Model
{
    protected $table = 'ext_chatbot_ecommerce_lifecycle_states';
    protected $guarded = [];
    protected $casts = [
        'preserve_data' => 'boolean',
        'disabled_at' => 'datetime',
        'uninstall_started_at' => 'datetime',
        'uninstalled_at' => 'datetime',
        'enabled_at' => 'datetime',
        'metadata' => 'array',
    ];
}
