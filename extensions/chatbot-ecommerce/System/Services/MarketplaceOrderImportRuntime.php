<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceOrderLineSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceOrderSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSyncCursor;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSyncRun;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MarketplaceOrderImportRuntime
{
    public function __construct(private readonly MarketplaceProviderRegistry $providers, private readonly UnifiedOrderProjectorRuntime $projector) {}

    public function import(MarketplaceConnection $connection): MarketplaceSyncRun
    {
        $cursor = MarketplaceSyncCursor::query()->firstOrCreate(['connection_id' => $connection->id, 'scope' => 'orders'], ['cursor' => []]);
        $run = MarketplaceSyncRun::query()->create([
            'chatbot_id' => $connection->chatbot_id, 'connection_id' => $connection->id, 'scope' => 'orders',
            'status' => 'running', 'cursor_before' => (array) $cursor->cursor, 'started_at' => now(),
        ]);
        $created = 0; $updated = 0; $read = 0;
        try {
            $response = $this->providers->get((string) $connection->provider)->importOrders($connection, (array) $cursor->cursor);
            foreach ((array) ($response['orders'] ?? []) as $payload) {
                if (! is_array($payload) || ($payload['external_order_id'] ?? '') === '') { continue; }
                $read++;
                DB::transaction(function () use ($connection, $payload, &$created, &$updated): void {
                    $existing = MarketplaceOrderSnapshot::query()->where('connection_id', $connection->id)->where('external_order_id', $payload['external_order_id'])->first();
                    $order = MarketplaceOrderSnapshot::query()->updateOrCreate(
                        ['connection_id' => $connection->id, 'external_order_id' => $payload['external_order_id']],
                        [
                            'chatbot_id' => $connection->chatbot_id, 'provider' => $connection->provider, 'status' => $payload['status'],
                            'currency' => $payload['currency'], 'total_amount' => $payload['total_amount'],
                            'buyer_display_name' => $payload['buyer']['display_name'] ?? null, 'fulfillment_status' => $payload['fulfillment_status'] ?? null,
                            'source_hash' => $payload['source_hash'], 'snapshot' => $payload, 'placed_at' => $payload['placed_at'] ?? null,
                            'provider_updated_at' => $payload['updated_at'] ?? null, 'first_imported_at' => $existing?->first_imported_at ?? now(), 'last_imported_at' => now(),
                        ]
                    );
                    $existing === null ? $created++ : $updated++;
                    foreach ((array) ($payload['lines'] ?? []) as $line) {
                        if (! is_array($line) || ($line['external_line_id'] ?? '') === '') { continue; }
                        MarketplaceOrderLineSnapshot::query()->updateOrCreate(
                            ['order_snapshot_id' => $order->id, 'external_line_id' => $line['external_line_id']],
                            ['sku' => $line['sku'] ?? null, 'title' => $line['title'], 'quantity' => $line['quantity'], 'unit_amount' => $line['unit_amount'], 'tax_amount' => $line['tax_amount'], 'snapshot' => $line]
                        );
                    }
                    $this->projector->projectMarketplace($order->fresh('lines'));
                });
            }
            $next = (array) ($response['next_cursor'] ?? []);
            $cursor->forceFill(['cursor' => $next, 'last_synced_at' => now()])->save();
            $run->forceFill(['status' => 'completed', 'records_read' => $read, 'records_created' => $created, 'records_updated' => $updated, 'cursor_after' => $next, 'completed_at' => now()])->save();
            $connection->forceFill(['last_read_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $e) {
            $run->forceFill(['status' => 'failed', 'records_read' => $read, 'records_created' => $created, 'records_updated' => $updated, 'failure_reason' => 'Marketplace order import failed: ' . hash('sha256', $e->getMessage()), 'completed_at' => now()])->save();
            $connection->forceFill(['last_error' => $run->failure_reason])->save();
        }
        return $run->fresh();
    }
}
