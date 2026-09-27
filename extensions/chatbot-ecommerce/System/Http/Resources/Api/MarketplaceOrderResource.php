<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
final class MarketplaceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid, 'provider' => $this->provider, 'external_order_id' => $this->external_order_id,
            'status' => $this->status, 'fulfillment_status' => $this->fulfillment_status, 'currency' => $this->currency,
            'total_amount' => (int) $this->total_amount, 'buyer_display_name' => $this->buyer_display_name,
            'placed_at' => $this->placed_at?->toIso8601String(), 'provider_updated_at' => $this->provider_updated_at?->toIso8601String(),
            'last_imported_at' => $this->last_imported_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line): array => [
                'uuid' => $line->uuid, 'external_line_id' => $line->external_line_id, 'sku' => $line->sku,
                'title' => $line->title, 'quantity' => (int) $line->quantity, 'unit_amount' => (int) $line->unit_amount,
                'tax_amount' => (int) $line->tax_amount,
            ])->values()),
        ];
    }
}
