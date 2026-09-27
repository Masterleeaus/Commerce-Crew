<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\OrderReconciliation;
use App\Extensions\ChatbotEcommerce\System\Models\OrderSettlementEntry;
use App\Extensions\ChatbotEcommerce\System\Models\UnifiedCommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Support\SettlementReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderSettlementRuntime
{
    /** @param array<int,array<string,mixed>> $entries @return array<string,mixed> */
    public function importEntries(Chatbot $chatbot, UnifiedCommerceOrder $order, string $provider, ?string $sourceScope, ?string $sourceBatchId, array $entries): array
    {
        $this->assertScope($chatbot, $order);
        $provider = strtolower(trim($provider));
        $sourceScope = trim((string) ($sourceScope ?? $order->source_scope ?? $provider));
        if ($sourceScope === '') { $sourceScope = $provider; }
        if ($provider === '' || $entries === []) {
            throw ValidationException::withMessages(['settlements' => 'A provider and at least one settlement entry are required.']);
        }

        $created = 0;
        $duplicates = 0;
        DB::transaction(function () use ($chatbot, $order, $provider, $sourceScope, $sourceBatchId, $entries, &$created, &$duplicates): void {
            foreach ($entries as $index => $entry) {
                if (! is_array($entry)) { continue; }
                $externalId = trim((string) ($entry['external_entry_id'] ?? $entry['id'] ?? ''));
                $type = strtolower(trim((string) ($entry['type'] ?? 'adjustment')));
                $currency = strtoupper(trim((string) ($entry['currency'] ?? $order->currency)));
                if ($externalId === '' || strlen($currency) !== 3) {
                    throw ValidationException::withMessages(["entries.{$index}" => 'Every settlement entry requires an external_entry_id and three-letter currency.']);
                }
                if ($currency !== strtoupper((string) $order->currency)) {
                    throw ValidationException::withMessages(["entries.{$index}.currency" => 'Settlement currency must match the order currency.']);
                }

                $sourceSnapshot = $entry;
                $encoded = json_encode($sourceSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                $sourceHash = hash('sha256', $encoded === false ? serialize($sourceSnapshot) : $encoded);
                $existing = OrderSettlementEntry::query()
                    ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
                    ->where('provider', $provider)
                    ->where('source_scope', $sourceScope)
                    ->where('external_entry_id', $externalId)
                    ->first();
                if ($existing !== null) {
                    if ((int) $existing->unified_order_id !== (int) $order->id || (string) $existing->source_hash !== $sourceHash) {
                        throw ValidationException::withMessages(["entries.{$index}" => 'The settlement entry ID was already used with different immutable source data.']);
                    }
                    $duplicates++;
                    continue;
                }

                OrderSettlementEntry::query()->create([
                    'chatbot_id' => (int) $chatbot->getAttribute('id'),
                    'unified_order_id' => $order->id,
                    'provider' => $provider,
                    'source_scope' => $sourceScope,
                    'external_entry_id' => $externalId,
                    'source_batch_id' => $sourceBatchId,
                    'entry_type' => $type,
                    'amount' => (int) ($entry['amount'] ?? 0),
                    'currency' => $currency,
                    'source_hash' => $sourceHash,
                    'source_snapshot' => $sourceSnapshot,
                    'occurred_at' => $entry['occurred_at'] ?? null,
                    'imported_at' => now(),
                ]);
                $created++;
            }
        });

        $reconciliation = $this->reconcile($chatbot, $order->fresh());
        return ['created' => $created, 'duplicates' => $duplicates, 'reconciliation' => $reconciliation->toArray()];
    }

    public function reconcile(Chatbot $chatbot, UnifiedCommerceOrder $order): OrderReconciliation
    {
        $this->assertScope($chatbot, $order);
        $entries = OrderSettlementEntry::query()->where('unified_order_id', $order->id)->orderBy('id')->get();
        $normalizedEntries = $entries->map(static fn (OrderSettlementEntry $entry): array => [
            'type' => $entry->entry_type,
            'amount' => (int) $entry->amount,
        ])->all();
        $hasGrossEntry = collect($normalizedEntries)->contains(static fn (array $entry): bool => in_array((string) $entry['type'], ['sale', 'gross', 'order'], true));
        if (! $hasGrossEntry) {
            $normalizedEntries[] = ['type' => 'sale', 'amount' => (int) $order->gross_total];
        }
        $calculation = SettlementReconciliation::calculate($normalizedEntries);
        $evidence = [
            'entry_uuids' => $entries->pluck('uuid')->all(),
            'entry_hashes' => $entries->pluck('source_hash')->all(),
            'order_source_hash' => $order->source_hash,
        ];
        $calculationHash = hash('sha256', json_encode([$calculation, $evidence], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

        $reconciliation = OrderReconciliation::query()->firstOrCreate(
            ['unified_order_id' => $order->id, 'calculation_hash' => $calculationHash],
            [
                'chatbot_id' => (int) $chatbot->getAttribute('id'),
                ...$calculation,
                'evidence' => $evidence,
                'reconciled_at' => now(),
            ]
        );

        $order->forceFill([
            'fee_total' => (int) $calculation['fee_amount'],
            'shipping_cost_total' => (int) $calculation['shipping_cost_amount'],
            'refund_total' => max((int) $order->refund_total, (int) $calculation['refund_amount']),
            'chargeback_total' => (int) $calculation['chargeback_amount'],
            'expected_net_payout' => (int) $calculation['expected_net_amount'],
            'reported_net_payout' => (int) $calculation['reported_net_amount'],
            'settlement_variance' => (int) $calculation['variance_amount'],
            'reconciliation_status' => (string) $calculation['status'],
        ])->save();

        return $reconciliation;
    }

    private function assertScope(Chatbot $chatbot, UnifiedCommerceOrder $order): void
    {
        abort_unless((int) $order->chatbot_id === (int) $chatbot->getAttribute('id'), 404);
    }
}
