<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UnifiedCommerceOrderResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'source' => ['type' => $this->source_type, 'scope' => $this->source_scope, 'order_id' => $this->source_order_id, 'external_authoritative' => (bool) $this->external_authoritative],
            'status' => $this->status,
            'fulfillment_status' => $this->fulfillment_status,
            'payment_status' => $this->payment_status,
            'currency' => $this->currency,
            'totals' => [
                'gross' => (int) $this->gross_total, 'discount' => (int) $this->discount_total, 'tax' => (int) $this->tax_total,
                'shipping' => (int) $this->shipping_total, 'refunds' => (int) $this->refund_total, 'chargebacks' => (int) $this->chargeback_total,
                'fees' => (int) $this->fee_total, 'shipping_cost' => (int) $this->shipping_cost_total,
                'expected_net_payout' => (int) $this->expected_net_payout, 'reported_net_payout' => (int) $this->reported_net_payout,
                'settlement_variance' => (int) $this->settlement_variance,
            ],
            'reconciliation_status' => $this->reconciliation_status,
            'buyer_display_name' => $this->buyer_display_name,
            'shipping_address' => $this->shipping_address,
            'placed_at' => $this->placed_at,
            'source_updated_at' => $this->source_updated_at,
            'last_projected_at' => $this->last_projected_at,
            'source_hash' => $this->source_hash,
            'open_exceptions' => OrderExceptionResource::collection($this->whenLoaded('exceptions')),
            'communication_threads' => $this->whenLoaded('communicationThreads', fn () => $this->communicationThreads->map(fn ($thread) => ['uuid' => $thread->uuid, 'status' => $thread->status, 'channel' => $thread->channel, 'intent' => $thread->intent])),
            'source_snapshots' => $this->whenLoaded('sourceSnapshots'),
            'settlements' => $this->whenLoaded('settlements'),
            'reconciliations' => $this->whenLoaded('reconciliations'),
            'actions' => $this->whenLoaded('actions'),
            'metadata' => $this->metadata,
        ];
    }
}
