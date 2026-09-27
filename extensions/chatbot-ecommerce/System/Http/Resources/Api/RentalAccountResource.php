<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'account_number' => $this->account_number,
            'account_type' => $this->account_type,
            'display_name' => $this->display_name,
            'currency' => $this->currency,
            'status' => $this->status,
            'chatbot_id' => $this->chatbot_id ? (int) $this->chatbot_id : null,
            'customer_identity_id' => $this->customer_identity_id ? (int) $this->customer_identity_id : null,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'metadata' => $this->metadata ?? [],
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
