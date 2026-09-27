<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $allocated = $this->relationLoaded('allocations')
            ? (int) $this->allocations->whereNull('reversed_at')->sum('amount')
            : null;

        return [
            'uuid' => $this->uuid,
            'payment_reference' => $this->payment_reference,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'provider' => $this->provider,
            'payment_url' => $this->payment_url,
            'status' => $this->status,
            'payment_intent_uuid' => $this->whenLoaded('paymentIntent', fn () => $this->paymentIntent?->uuid),
            'instructions' => $this->instructions ?? [],
            'allocated_amount' => $allocated,
            'unallocated_credit' => $allocated === null ? null : max(0, (int) $this->amount - $allocated),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->whereNull('reversed_at')->map(fn ($allocation) => [
                'uuid' => $allocation->uuid,
                'charge_uuid' => $allocation->charge?->uuid,
                'charge_number' => $allocation->charge?->charge_number,
                'amount' => (int) $allocation->amount,
                'period_start' => $allocation->charge?->period_start?->toDateString(),
                'period_end' => $allocation->charge?->period_end?->toDateString(),
            ])->values()),
            'receipt_uuid' => $this->whenLoaded('receipt', fn () => $this->receipt?->uuid),
            'requested_at' => $this->requested_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
