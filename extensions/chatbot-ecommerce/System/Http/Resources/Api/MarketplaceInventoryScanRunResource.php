<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MarketplaceInventoryScanRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'=>$this->uuid,'status'=>$this->status,
            'counts'=>['mappings'=>(int)$this->mapping_count,'in_sync'=>(int)$this->in_sync_count,'conflicts'=>(int)$this->conflict_count,'critical'=>(int)$this->critical_count,'corrections_prepared'=>(int)$this->corrections_prepared],
            'started_at'=>$this->started_at?->toIso8601String(),'completed_at'=>$this->completed_at?->toIso8601String(),
            'metadata'=>$this->metadata ?? [],
            'conflicts'=>MarketplaceInventoryConflictResource::collection($this->whenLoaded('conflicts')),
        ];
    }
}
