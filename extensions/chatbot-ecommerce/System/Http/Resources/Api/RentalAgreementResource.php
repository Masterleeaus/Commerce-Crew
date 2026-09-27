<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalAgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'account_uuid' => $this->whenLoaded('account', fn () => $this->account?->uuid),
            'agreement_number' => $this->agreement_number,
            'agreement_type' => $this->agreement_type,
            'subject_type' => $this->subject_type,
            'subject_reference' => $this->subject_reference,
            'subject_description' => $this->subject_description,
            'billing_frequency' => $this->billing_frequency,
            'billing_interval' => (int) $this->billing_interval,
            'custom_interval_days' => $this->custom_interval_days ? (int) $this->custom_interval_days : null,
            'charge_amount' => (int) $this->charge_amount,
            'currency' => $this->currency,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'next_charge_date' => $this->next_charge_date?->toDateString(),
            'due_offset_days' => (int) $this->due_offset_days,
            'status' => $this->status,
            'allow_partial_payments' => (bool) $this->allow_partial_payments,
            'auto_allocate_payments' => (bool) $this->auto_allocate_payments,
            'metadata' => $this->metadata ?? [],
            'rates' => $this->whenLoaded('rates', fn () => $this->rates->map(fn ($rate) => [
                'uuid' => $rate->uuid,
                'amount' => (int) $rate->amount,
                'currency' => $rate->currency,
                'effective_from' => $rate->effective_from?->toDateString(),
                'effective_to' => $rate->effective_to?->toDateString(),
                'reason' => $rate->reason,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
