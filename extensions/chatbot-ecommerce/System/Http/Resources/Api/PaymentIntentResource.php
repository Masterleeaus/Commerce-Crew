<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentIntentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'scope' => $this->checkout_session_id ? 'checkout' : ($this->rental_payment_id ? 'rental_hire' : 'unscoped'),
            'checkout_uuid' => $this->whenLoaded('checkout', fn () => $this->checkout?->uuid),
            'rental_payment_uuid' => $this->whenLoaded('rentalPayment', fn () => $this->rentalPayment?->uuid),
            'provider' => $this->provider,
            'method' => $this->method,
            'status' => $this->status,
            'amount' => (int) $this->amount,
            'authorized_amount' => (int) $this->authorized_amount,
            'captured_amount' => (int) $this->captured_amount,
            'refunded_amount' => (int) $this->refunded_amount,
            'refundable_amount' => max(0, (int) $this->captured_amount - (int) $this->refunded_amount),
            'currency' => $this->currency,
            'payment_url' => $this->payment_url,
            'instructions' => $this->instructions ?? [],
            'bnpl_offer' => $this->whenLoaded('bnplOffer', fn () => $this->bnplOffer ? [
                'uuid' => $this->bnplOffer->uuid,
                'status' => $this->bnplOffer->status,
                'installment_count' => (int) $this->bnplOffer->installment_count,
                'installment_schedule' => $this->bnplOffer->installment_schedule ?? [],
                'approval_url' => $this->bnplOffer->approval_url,
            ] : null),
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'refunds' => $this->whenLoaded('refunds', fn () => $this->refunds->map(fn ($refund) => [
                'uuid' => $refund->uuid,
                'amount' => (int) $refund->amount,
                'currency' => $refund->currency,
                'status' => $refund->status,
                'reason' => $refund->reason,
                'processed_at' => $refund->processed_at?->toIso8601String(),
            ])),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'authorized_at' => $this->authorized_at?->toIso8601String(),
            'captured_at' => $this->captured_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
