<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MarketplaceBulkBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'operation' => $this->operation,
            'status' => $this->status,
            'selection_filters' => $this->selection_filters ?? [],
            'change_template' => $this->change_template ?? [],
            'impact_summary' => $this->impact_summary ?? [],
            'counts' => [
                'selected' => (int) $this->selected_count,
                'eligible' => (int) $this->eligible_count,
                'blocked' => (int) $this->blocked_count,
                'succeeded' => (int) $this->succeeded_count,
                'failed' => (int) $this->failed_count,
                'rolled_back' => (int) $this->rolled_back_count,
            ],
            'approval_expires_at' => $this->approval_expires_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'rolled_back_at' => $this->rolled_back_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'uuid' => $item->uuid,
                'external_listing_id' => $item->external_listing_id,
                'status' => $item->status,
                'before_state' => $item->before_state ?? [],
                'proposed_changes' => $item->proposed_changes ?? [],
                'impact' => $item->impact ?? [],
                'conflicts' => $item->conflicts ?? [],
                'proposal_uuid' => $item->proposal?->uuid,
                'executed_at' => $item->executed_at?->toIso8601String(),
                'rolled_back_at' => $item->rolled_back_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
