<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CommerceCommunicationThreadResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'chatbot_id' => (int) $this->chatbot_id,
            'customer_identity_id' => $this->customer_identity_id ? (int) $this->customer_identity_id : null,
            'conversation_id' => $this->conversation_id ? (int) $this->conversation_id : null,
            'session_id' => $this->session_id,
            'role' => $this->role,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'intent' => $this->intent,
            'sentiment' => $this->sentiment,
            'confidence' => $this->confidence !== null ? (float) $this->confidence : null,
            'identity_verified' => (bool) $this->identity_verified,
            'assigned_to_type' => $this->assigned_to_type,
            'assigned_to_id' => $this->assigned_to_id,
            'last_message_at' => $this->last_message_at,
            'first_response_at' => $this->first_response_at,
            'resolved_at' => $this->resolved_at,
            'metadata' => $this->metadata,
            'messages' => $this->whenLoaded('messages'),
            'actions' => $this->whenLoaded('actions'),
            'escalations' => $this->whenLoaded('escalations'),
        ];
    }
}
