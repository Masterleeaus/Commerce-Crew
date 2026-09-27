<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CommerceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'source' => $this->source,
            'status' => $this->status,
            'fulfillment_status' => $this->fulfillment_status,
            'currency' => $this->currency,
            'subtotal' => (int) $this->subtotal,
            'discount_total' => (int) $this->discount_total,
            'tax_total' => (int) $this->tax_total,
            'shipping_total' => (int) $this->shipping_total,
            'total' => (int) $this->total,
            'refunded_total' => (int) $this->refunded_total,
            'customer' => $this->customer_snapshot ?? [],
            'billing_address' => $this->billing_address ?? [],
            'shipping_address' => $this->shipping_address ?? [],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'uuid' => $item->uuid,
                'product_id' => $item->product_id ? (int) $item->product_id : null,
                'variant_id' => $item->variant_id ? (int) $item->variant_id : null,
                'sku' => $item->sku,
                'name' => $item->name,
                'variant_name' => $item->variant_name,
                'quantity' => (int) $item->quantity,
                'fulfilled_quantity' => (int) $item->fulfilled_quantity,
                'returned_quantity' => (int) $item->returned_quantity,
                'unit_price' => (int) $item->unit_price,
                'discount_total' => (int) $item->discount_total,
                'tax_total' => (int) $item->tax_total,
                'line_total' => (int) $item->line_total,
            ])->values()),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event): array => [
                'uuid' => $event->uuid,
                'type' => $event->event_type,
                'after_state' => $event->after_state ?? [],
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()),
            'returns' => $this->whenLoaded('returns', fn () => $this->returns->map(fn ($return): array => [
                'uuid' => $return->uuid,
                'rma_number' => $return->rma_number,
                'status' => $return->status,
                'requested_resolution' => $return->requested_resolution,
                'requested_amount' => (int) $return->requested_amount,
                'refunded_amount' => (int) $return->refunded_amount,
                'created_at' => $return->created_at?->toIso8601String(),
            ])->values()),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
