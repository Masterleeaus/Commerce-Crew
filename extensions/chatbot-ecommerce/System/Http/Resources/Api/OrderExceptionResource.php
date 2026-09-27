<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderExceptionResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'order_uuid' => $this->relationLoaded('order') ? $this->order?->uuid : null,
            'type' => $this->exception_type,
            'severity' => $this->severity,
            'status' => $this->status,
            'title' => $this->title,
            'summary' => $this->summary,
            'evidence' => $this->evidence,
            'assigned_user_id' => $this->assigned_user_id,
            'acknowledged_at' => $this->acknowledged_at,
            'resolved_at' => $this->resolved_at,
            'first_detected_at' => $this->first_detected_at,
            'last_detected_at' => $this->last_detected_at,
            'events' => $this->whenLoaded('events'),
        ];
    }
}
