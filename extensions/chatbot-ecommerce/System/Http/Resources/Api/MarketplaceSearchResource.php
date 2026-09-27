<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
final class MarketplaceSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid, 'query' => $this->query, 'filters' => $this->filters ?? [], 'providers' => $this->providers ?? [],
            'status' => $this->status, 'cache_hit' => (bool) $this->cache_hit,
            'progress' => ['completed' => (int) $this->completed_provider_count, 'total' => (int) $this->provider_count],
            'result_count' => (int) $this->result_count, 'errors' => $this->errors ?? [],
            'results' => $this->whenLoaded('results', fn () => $this->results->map(fn ($r): array => [
                'uuid' => $r->uuid, 'provider' => $r->provider, 'external_listing_id' => $r->external_listing_id,
                'title' => $r->title, 'description' => $r->description, 'price_amount' => (int) $r->price_amount,
                'currency' => $r->currency, 'image_url' => $r->image_url, 'product_url' => $r->product_url,
                'availability' => $r->availability, 'seller_name' => $r->seller_name,
                'shipping_summary' => $r->shipping_summary, 'bnpl_summary' => $r->bnpl_summary,
                'attributes' => $r->attributes ?? [], 'relevance_score' => (float) $r->relevance_score,
            ])->values()),
            'expires_at' => $this->expires_at?->toIso8601String(), 'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
