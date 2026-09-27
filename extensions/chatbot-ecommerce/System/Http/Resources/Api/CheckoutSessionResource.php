<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'cart_uuid' => $this->whenLoaded('cart', fn () => $this->cart->uuid),
            'status' => $this->status,
            'email' => $this->email,
            'phone' => $this->phone,
            'billing_address' => $this->billing_address ?? [],
            'shipping_address' => $this->shipping_address ?? [],
            'delivery_method' => $this->delivery_method,
            'delivery_option' => $this->delivery_option ?? [],
            'shipping_quote_uuid' => $this->whenLoaded('shippingQuote', fn () => $this->shippingQuote?->uuid),
            'fulfillment_status' => $this->fulfillment_status ?? 'unfulfilled',
            'customer_note' => $this->customer_note,
            'consent' => $this->consent ?? [],
            'currency' => $this->currency,
            'cart_version' => (int) $this->cart_version,
            'pricing_snapshot_uuid' => $this->pricing_snapshot_uuid,
            'pricing_snapshot_hash' => $this->pricing_snapshot_hash,
            'subtotal' => (int) $this->subtotal,
            'discount_total' => (int) $this->discount_total,
            'tax_total' => (int) $this->tax_total,
            'tax_zone_id' => $this->tax_zone_id ? (int) $this->tax_zone_id : null,
            'tax_context_hash' => $this->tax_context_hash,
            'tax_breakdown' => $this->tax_breakdown ?? [],
            'shipping_total' => (int) $this->shipping_total,
            'total' => (int) $this->total,
            'inventory_snapshot' => $this->inventory_snapshot ?? [],
            'order_reference' => $this->order_reference,
            'failure_code' => $this->failure_code,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'approval_expires_at' => $this->approval_expires_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'payment_intent_uuid' => $this->whenLoaded('paymentIntent', fn () => $this->paymentIntent?->uuid),
            'payment_status' => $this->payment_status,
            'payment_pending_at' => $this->payment_pending_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
