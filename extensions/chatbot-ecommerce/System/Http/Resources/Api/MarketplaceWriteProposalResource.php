<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MarketplaceWriteProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'external_listing_id' => $this->external_listing_id,
            'operation' => $this->operation,
            'status' => $this->status,
            'action_hash' => $this->action_hash,
            'expected_source_hash' => $this->expected_source_hash,
            'after_source_hash' => $this->after_source_hash,
            'requested_changes' => $this->requested_changes ?? [],
            'before_state' => $this->before_state ?? [],
            'after_state' => $this->after_state,
            'conflict_state' => $this->conflict_state ?? ['blocking' => false, 'conflicts' => [], 'warnings' => []],
            'approval_expires_at' => $this->approval_expires_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'queued_at' => $this->queued_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'rolled_back_at' => $this->rolled_back_at?->toIso8601String(),
            'provider_result' => $this->provider_result ?? [],
            'attempts' => $this->whenLoaded('attempts', fn () => $this->attempts->map(fn ($attempt) => [
                'uuid' => $attempt->uuid,
                'phase' => $attempt->phase,
                'status' => $attempt->status,
                'attempt_number' => (int) $attempt->attempt_number,
                'provider_request_id' => $attempt->provider_request_id,
                'retry_after_seconds' => $attempt->retry_after_seconds,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
