<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ListingIntelligenceRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'=>$this->uuid,'provider'=>$this->provider,'external_listing_id'=>$this->external_listing_id,'status'=>$this->status,
            'source_hash'=>$this->source_hash,'source_content'=>$this->source_content ?? [],'generated_content'=>$this->generated_content ?? [],
            'evidence_report'=>$this->evidence_report ?? [],'compliance_summary'=>$this->compliance_summary ?? [],
            'findings'=>$this->whenLoaded('findings', fn()=> $this->findings->map(fn($f)=>[
                'uuid'=>$f->uuid,'code'=>$f->code,'severity'=>$f->severity,'field'=>$f->field,'matched_value'=>$f->matched_value,'message'=>$f->message,'remediation'=>$f->remediation,
            ])),
            'rewrites'=>$this->whenLoaded('rewrites', fn()=> $this->rewrites->map(fn($r)=>[
                'uuid'=>$r->uuid,'status'=>$r->status,'source_hash'=>$r->source_hash,'before_content'=>$r->before_content ?? [],'proposed_content'=>$r->proposed_content ?? [],
                'compliance_summary'=>$r->compliance_summary ?? [],'marketplace_write_proposal_id'=>$r->marketplace_write_proposal_id,'prepared_at'=>$r->prepared_at?->toIso8601String(),
            ])),
            'completed_at'=>$this->completed_at?->toIso8601String(),'created_at'=>$this->created_at?->toIso8601String(),
        ];
    }
}
