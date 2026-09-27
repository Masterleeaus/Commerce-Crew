<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryConflict;
use App\Extensions\ChatbotEcommerce\System\Models\OrderException;
use App\Extensions\ChatbotEcommerce\System\Models\OrderExceptionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\UnifiedCommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Support\OrderExceptionDetector;
use Illuminate\Support\Facades\DB;

final class OrderExceptionRuntime
{
    /** @return array<string,int> */
    public function scanChatbot(Chatbot $chatbot): array
    {
        $opened = $resolved = $scanned = 0;
        UnifiedCommerceOrder::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->orderBy('id')->chunkById(100, function ($orders) use ($chatbot, &$opened, &$resolved, &$scanned): void {
            foreach ($orders as $order) {
                $result = $this->scanOrder($chatbot, $order);
                $opened += $result['opened'];
                $resolved += $result['resolved'];
                $scanned++;
            }
        });
        return ['orders_scanned' => $scanned, 'exceptions_opened' => $opened, 'exceptions_resolved' => $resolved];
    }

    /** @return array<string,int> */
    public function scanOrder(Chatbot $chatbot, UnifiedCommerceOrder $order): array
    {
        $this->assertScope($chatbot, $order);
        $stockConflict = (bool) ($order->metadata['stock_conflict'] ?? false);
        if (! $stockConflict && $order->connection_id !== null) {
            $stockConflict = MarketplaceInventoryConflict::query()
                ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
                ->where('connection_id', $order->connection_id)
                ->whereIn('status', ['open', 'acknowledged'])
                ->exists();
        }
        $returnDisputed = (bool) ($order->source_snapshot['return_disputed'] ?? false);
        if (! $returnDisputed && $order->native_order_id !== null) {
            $returnDisputed = DB::table('ext_chatbot_commerce_returns')
                ->where('order_id', $order->native_order_id)
                ->whereIn('status', ['disputed', 'rejected', 'chargeback'])
                ->exists();
        }
        $detected = OrderExceptionDetector::detect([
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'placed_at_timestamp' => $order->placed_at?->getTimestamp() ?? 0,
            'shipping_address' => $order->shipping_address ?? [],
            'stock_conflict' => $stockConflict,
            'return_disputed' => $returnDisputed,
            'reconciliation_status' => $order->reconciliation_status,
        ], now()->getTimestamp(), (int) config('chatbot-ecommerce.order_workbench.fulfillment_delay_seconds', 172800));

        $activeFingerprints = [];
        $opened = 0;
        foreach ($detected as $item) {
            $fingerprint = hash('sha256', $order->id . ':' . $item['type']);
            $activeFingerprints[] = $fingerprint;
            $exception = OrderException::query()->where('unified_order_id', $order->id)->where('fingerprint', $fingerprint)->first();
            $before = $exception?->toArray();
            if ($exception === null) {
                $exception = OrderException::query()->create([
                    'chatbot_id' => (int) $chatbot->getAttribute('id'),
                    'unified_order_id' => $order->id,
                    'exception_type' => $item['type'],
                    'fingerprint' => $fingerprint,
                    'severity' => $item['severity'],
                    'status' => 'open',
                    'title' => $this->title((string) $item['type']),
                    'summary' => $item['message'],
                    'evidence' => $this->evidence($order, (string) $item['type']),
                    'first_detected_at' => now(),
                    'last_detected_at' => now(),
                ]);
                $opened++;
                $this->event($exception, 'detected', null, null, [], $exception->toArray());
            } else {
                $wasResolved = $exception->status === 'resolved';
                $exception->forceFill([
                    'severity' => $item['severity'],
                    'status' => $wasResolved ? 'open' : $exception->status,
                    'summary' => $item['message'],
                    'evidence' => $this->evidence($order, (string) $item['type']),
                    'last_detected_at' => now(),
                    'resolved_at' => $wasResolved ? null : $exception->resolved_at,
                    'resolved_by_user_id' => $wasResolved ? null : $exception->resolved_by_user_id,
                ])->save();
                if ($wasResolved) { $opened++; $this->event($exception, 'reopened', null, null, $before ?? [], $exception->toArray()); }
            }
        }

        $resolved = 0;
        $query = OrderException::query()->where('unified_order_id', $order->id)->whereIn('status', ['open', 'acknowledged']);
        if ($activeFingerprints !== []) { $query->whereNotIn('fingerprint', $activeFingerprints); }
        $query->get()->each(function (OrderException $exception) use (&$resolved): void {
            $before = $exception->toArray();
            $exception->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'metadata' => array_merge((array) $exception->metadata, ['auto_resolved' => true])])->save();
            $this->event($exception, 'auto_resolved', 'system', null, $before, $exception->toArray());
            $resolved++;
        });

        return ['opened' => $opened, 'resolved' => $resolved, 'active' => count($activeFingerprints)];
    }

    public function acknowledge(Chatbot $chatbot, OrderException $exception, int $userId): OrderException
    {
        $this->assertExceptionScope($chatbot, $exception);
        $before = $exception->toArray();
        $exception->forceFill(['status' => 'acknowledged', 'acknowledged_by_user_id' => $userId, 'acknowledged_at' => now()])->save();
        $this->event($exception, 'acknowledged', 'user', $userId, $before, $exception->toArray());
        return $exception->fresh();
    }

    public function assign(Chatbot $chatbot, OrderException $exception, int $assigneeUserId, int $actorUserId): OrderException
    {
        $this->assertExceptionScope($chatbot, $exception);
        $before = $exception->toArray();
        $exception->forceFill(['assigned_user_id' => $assigneeUserId])->save();
        $this->event($exception, 'assigned', 'user', $actorUserId, $before, $exception->toArray(), ['assignee_user_id' => $assigneeUserId]);
        return $exception->fresh();
    }

    public function resolve(Chatbot $chatbot, OrderException $exception, int $userId, ?string $note = null): OrderException
    {
        $this->assertExceptionScope($chatbot, $exception);
        $before = $exception->toArray();
        $exception->forceFill(['status' => 'resolved', 'resolved_by_user_id' => $userId, 'resolved_at' => now(), 'metadata' => array_merge((array) $exception->metadata, ['resolution_note' => $note])])->save();
        $this->event($exception, 'resolved', 'user', $userId, $before, $exception->toArray(), ['note' => $note]);
        return $exception->fresh();
    }

    private function assertScope(Chatbot $chatbot, UnifiedCommerceOrder $order): void { abort_unless((int) $order->chatbot_id === (int) $chatbot->getAttribute('id'), 404); }
    private function assertExceptionScope(Chatbot $chatbot, OrderException $exception): void { abort_unless((int) $exception->chatbot_id === (int) $chatbot->getAttribute('id'), 404); }

    /** @return array<string,mixed> */
    private function evidence(UnifiedCommerceOrder $order, string $type): array
    {
        return ['type' => $type, 'source_hash' => $order->source_hash, 'source_type' => $order->source_type, 'source_order_id' => $order->source_order_id, 'reconciliation_status' => $order->reconciliation_status];
    }

    private function title(string $type): string
    {
        return match ($type) {
            'invalid_address' => 'Invalid or incomplete delivery address',
            'payment_failure' => 'Payment failure',
            'fulfillment_delay' => 'Fulfilment delay',
            'stock_conflict' => 'Stock conflict',
            'disputed_return' => 'Disputed return',
            'settlement_variance' => 'Settlement variance',
            default => 'Order exception',
        };
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after @param array<string,mixed> $metadata */
    private function event(OrderException $exception, string $type, ?string $actorType, ?int $actorId, array $before, array $after, array $metadata = []): void
    {
        OrderExceptionEvent::query()->create(['exception_id' => $exception->id, 'event_type' => $type, 'actor_type' => $actorType, 'actor_id' => $actorId, 'before_state' => $before, 'after_state' => $after, 'metadata' => $metadata, 'occurred_at' => now()]);
    }
}
