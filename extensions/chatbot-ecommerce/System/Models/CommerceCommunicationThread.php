<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class CommerceCommunicationThread extends Model
{
    protected $table = 'ext_chatbot_commerce_communication_threads';
    protected $guarded = [];
    protected $casts = [
        'identity_verified' => 'boolean', 'confidence' => 'float', 'metadata' => 'array',
        'last_message_at' => 'datetime', 'first_response_at' => 'datetime',
        'resolved_at' => 'datetime', 'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function unifiedOrder(): BelongsTo { return $this->belongsTo(UnifiedCommerceOrder::class, 'unified_order_id'); }

    public function messages(): HasMany { return $this->hasMany(CommerceCommunicationMessage::class, 'thread_id')->orderBy('id'); }
    public function actions(): HasMany { return $this->hasMany(CommerceCommunicationAction::class, 'thread_id')->orderByDesc('id'); }
    public function escalations(): HasMany { return $this->hasMany(CommerceEscalation::class, 'thread_id')->orderByDesc('id'); }
}
