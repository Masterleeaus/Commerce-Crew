<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MarketplaceInventoryConflictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'=>$this->uuid,'provider'=>$this->provider,'external_listing_id'=>$this->external_listing_id,
            'code'=>$this->code,'severity'=>$this->severity,'status'=>$this->status,
            'canonical_quantity'=>(int)$this->canonical_quantity,'target_quantity'=>(int)$this->target_quantity,
            'external_quantity'=>(int)$this->external_quantity,'delta'=>(int)$this->delta,
            'oversell_exposure'=>(int)$this->oversell_exposure,'canonical_version'=>(int)$this->canonical_version,
            'source_hash'=>$this->source_hash,'assessment'=>$this->assessment ?? [],
            'mapping'=>$this->whenLoaded('mapping', fn()=>[
                'uuid'=>$this->mapping->uuid,'sku'=>$this->mapping->sku,'variant_id'=>(int)$this->mapping->variant_id,
                'location_id'=>$this->mapping->location_id === null ? null : (int)$this->mapping->location_id,
                'verified'=>(bool)$this->mapping->verified,
            ]),
            'write_proposal_uuid'=>$this->writeProposal?->uuid,
            'first_detected_at'=>$this->first_detected_at?->toIso8601String(),'last_detected_at'=>$this->last_detected_at?->toIso8601String(),
            'acknowledged_at'=>$this->acknowledged_at?->toIso8601String(),'ignored_until'=>$this->ignored_until?->toIso8601String(),
            'resolved_at'=>$this->resolved_at?->toIso8601String(),'resolution_mode'=>$this->resolution_mode,
            'events'=>$this->whenLoaded('events', fn()=> $this->events->map(fn($event)=>[
                'uuid'=>$event->uuid,'event_type'=>$event->event_type,'actor_type'=>$event->actor_type,
                'status_before'=>$event->status_before,'status_after'=>$event->status_after,'payload'=>$event->payload ?? [],
                'occurred_at'=>$event->occurred_at?->toIso8601String(),
            ])),
        ];
    }
}
