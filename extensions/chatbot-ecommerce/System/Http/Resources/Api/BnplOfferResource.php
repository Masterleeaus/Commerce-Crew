<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BnplOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'scope' => $this->scope,
            'shopping_mode' => $this->shopping_mode,
            'status' => $this->status,
            'provider' => $this->whenLoaded('providerProfile', fn () => [
                'code' => $this->providerProfile?->code,
                'name' => $this->providerProfile?->display_name,
                'type' => $this->providerProfile?->provider_type,
                'licence_reference' => $this->providerProfile?->licence_reference,
                'terms_url' => $this->providerProfile?->terms_url,
                'privacy_url' => $this->providerProfile?->privacy_url,
                'hardship_url' => $this->providerProfile?->hardship_url,
                'complaints_url' => $this->providerProfile?->complaints_url,
            ]),
            'amount' => (int) $this->amount,
            'customer_fee' => (int) $this->customer_fee,
            'total_payable' => (int) $this->total_payable,
            'currency' => $this->currency,
            'installment_count' => (int) $this->installment_count,
            'interval_days' => (int) $this->interval_days,
            'first_installment_amount' => (int) $this->first_installment_amount,
            'regular_installment_amount' => (int) $this->regular_installment_amount,
            'installment_schedule' => $this->installment_schedule ?? [],
            'approval_url' => $this->approval_url,
            'payment_intent_uuid' => $this->whenLoaded('paymentIntent', fn () => $this->paymentIntent?->uuid),
            'provider_reference' => $this->provider_reference,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'selected_at' => $this->selected_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'captured_at' => $this->captured_at?->toIso8601String(),
            'declined_at' => $this->declined_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
