<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ChatbotCartResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return [
            'uuid' => $this->getAttribute('uuid'),
            'chatbot_id' => $this->getAttribute('chatbot_id'),
            'session_id' => $this->getAttribute('session_id'),
            'product_source' => $this->getAttribute('product_source'),
            'status' => $this->getAttribute('status'),
            'currency' => $this->getAttribute('currency'),
            'coupon_code' => $this->getAttribute('coupon_code'),
            'line_count' => (int) $this->getAttribute('line_count'),
            'list_subtotal' => (int) $this->getAttribute('list_subtotal'),
            'subtotal' => (int) $this->getAttribute('subtotal'),
            'item_discount_total' => (int) $this->getAttribute('item_discount_total'),
            'discount_total' => (int) $this->getAttribute('discount_total'),
            'tax_total' => (int) $this->getAttribute('tax_total'),
            'tax_zone_id' => $this->getAttribute('tax_zone_id') ? (int) $this->getAttribute('tax_zone_id') : null,
            'tax_context_hash' => $this->getAttribute('tax_context_hash'),
            'tax_breakdown' => $this->getAttribute('tax_breakdown') ?? [],
            'shipping_subtotal' => (int) $this->getAttribute('shipping_subtotal'),
            'shipping_discount_total' => (int) $this->getAttribute('shipping_discount_total'),
            'shipping_total' => (int) $this->getAttribute('shipping_total'),
            'total' => (int) $this->getAttribute('total'),
            'version' => (int) $this->getAttribute('version'),
            'calculation_version' => $this->getAttribute('calculation_version'),
            'pricing_snapshot_uuid' => $this->getAttribute('pricing_snapshot_uuid'),
            'pricing_snapshot_hash' => $this->getAttribute('pricing_snapshot_hash'),
            'lines' => $this->whenLoaded('cartLines', fn () => $this->cartLines->map(fn ($line): array => [
                'uuid' => $line->uuid,
                'product_id' => (int) $line->product_id,
                'variant_id' => (int) $line->variant_id,
                'sku' => $line->sku,
                'name' => $line->name,
                'variant_name' => $line->variant_name,
                'quantity' => (int) $line->quantity,
                'list_unit_price' => (int) ($line->list_unit_price ?? $line->unit_price),
                'unit_price' => (int) $line->unit_price,
                'gross_total' => (int) ($line->gross_total ?? ($line->unit_price * $line->quantity)),
                'discounts' => $line->discounts ?? [],
                'discount_total' => (int) $line->discount_total,
                'net_before_tax' => (int) ($line->net_before_tax ?? 0),
                'tax_rate_bps' => (int) ($line->tax_rate_bps ?? 0),
                'tax_total' => (int) $line->tax_total,
                'tax_components' => (array) (($line->price_snapshot ?? [])['tax_components'] ?? []),
                'tax_exempt' => (bool) (($line->price_snapshot ?? [])['tax_exempt'] ?? false),
                'line_total' => (int) $line->line_total,
                'currency' => $line->currency,
                'customisation' => $line->customisation ?? [],
                'metadata' => $line->metadata ?? [],
            ])->values()),
            'expires_at' => $this->getAttribute('expires_at')?->toIso8601String(),
            'last_activity_at' => $this->getAttribute('last_activity_at')?->toIso8601String(),
            'created_at' => $this->getAttribute('created_at')?->timezone($this->timezone())->toIso8601String(),
            'updated_at' => $this->getAttribute('updated_at')?->timezone($this->timezone())->toIso8601String(),
        ];
    }

    public function timezone(): array|string
    {
        $timezone = request()->header('x-timezone');
        return is_string($timezone) ? $timezone : 'UTC';
    }
}
