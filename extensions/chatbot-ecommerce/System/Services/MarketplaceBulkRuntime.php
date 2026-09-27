<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceBulkBatch;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceBulkItem;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceListingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceWriteProposal;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceBulkImpact;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceBulkSelector;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceBulkState;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteApprovalToken;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteOperation;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MarketplaceBulkRuntime
{
    public function __construct(private readonly MarketplaceWriteRuntime $writes) {}

    /** @param array<string,mixed> $filters @param array<string,mixed> $template
     * @return array{batch:MarketplaceBulkBatch,approval_token:?string}
     */
    public function preview(Chatbot $chatbot, MarketplaceConnection $connection, string $operation, array $filters, array $template, string $idempotencyKey, int $userId): array
    {
        $this->assertEnabled();
        $this->assertConnection($chatbot, $connection, $userId);
        $operation = MarketplaceWriteOperation::capability($operation);
        $limit = max(1, (int) config('chatbot-ecommerce.marketplaces.write.bulk.maximum_items', 250));
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'A bounded idempotency key is required.']);
        }
        $batchHash = hash('sha256', json_encode([$connection->uuid, $operation, $filters, $template], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existing = MarketplaceBulkBatch::query()->where('connection_id', $connection->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            if (! hash_equals((string) $existing->batch_hash, $batchHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key belongs to another bulk action.']);
            }
            return $this->issueApproval($existing, $userId);
        }

        $rows = MarketplaceListingSnapshot::query()->where('connection_id', $connection->id)->orderByDesc('last_seen_at')->limit($limit * 4)->get();
        $normalized = $rows->map(function (MarketplaceListingSnapshot $row): array {
            $snapshot = (array) $row->snapshot;
            $attributes = (array) ($snapshot['attributes'] ?? []);
            return [
                'model' => $row,
                'external_listing_id' => (string) $row->external_listing_id,
                'provider' => (string) ($snapshot['provider'] ?? ''),
                'status' => (string) ($snapshot['status'] ?? $snapshot['availability'] ?? ''),
                'brand' => (string) ($attributes['brand'] ?? ''),
                'category' => (string) ($attributes['category'] ?? ''),
                'price_minor' => (int) ($snapshot['price_amount'] ?? 0),
            ];
        })->all();
        $selected = array_slice(MarketplaceBulkSelector::filter($normalized, $filters), 0, $limit);
        if ($selected === []) {
            throw ValidationException::withMessages(['filters' => 'No marketplace listings matched the bulk selection.']);
        }

        return DB::transaction(function () use ($chatbot, $connection, $operation, $filters, $template, $idempotencyKey, $batchHash, $selected, $userId): array {
            $batch = MarketplaceBulkBatch::query()->create([
                'chatbot_id' => (int) $chatbot->getAttribute('id'), 'connection_id' => $connection->id,
                'owner_user_id' => $userId, 'provider' => $connection->provider, 'operation' => $operation,
                'status' => MarketplaceBulkState::DRAFT, 'idempotency_key' => $idempotencyKey, 'batch_hash' => $batchHash,
                'selection_filters' => $filters, 'change_template' => $template, 'selected_count' => count($selected),
            ]);
            $eligible = $blocked = 0;
            $totalBefore = $totalAfter = 0;
            foreach ($selected as $entry) {
                /** @var MarketplaceListingSnapshot $listing */
                $listing = $entry['model'];
                $changes = $this->changesFor($operation, (array) $listing->snapshot, $template);
                $impact = $this->impactFor($operation, (array) $listing->snapshot, $changes);
                $result = $this->writes->prepare($chatbot, $connection, (string) $listing->external_listing_id, $operation, $changes, (string) $listing->source_hash, 'bulk:'.$batch->uuid.':'.$listing->uuid, $userId);
                $proposal = $result['proposal'];
                $isBlocked = (string) $proposal->status === MarketplaceWriteState::CONFLICT_BLOCKED;
                $isBlocked ? $blocked++ : $eligible++;
                $totalBefore += (int) ($impact['before'] ?? 0);
                $totalAfter += (int) ($impact['after'] ?? 0);
                MarketplaceBulkItem::query()->create([
                    'batch_id' => $batch->id, 'listing_snapshot_id' => $listing->id, 'proposal_id' => $proposal->id,
                    'external_listing_id' => $listing->external_listing_id, 'status' => $isBlocked ? 'blocked' : 'ready',
                    'expected_source_hash' => $listing->source_hash, 'before_state' => $proposal->before_state,
                    'proposed_changes' => $changes, 'impact' => $impact, 'conflicts' => $proposal->conflict_state,
                ]);
            }
            $status = $eligible > 0 ? MarketplaceBulkState::READY : MarketplaceBulkState::CONFLICT_BLOCKED;
            $batch->forceFill([
                'status' => $status, 'eligible_count' => $eligible, 'blocked_count' => $blocked,
                'impact_summary' => ['total_before_minor' => $totalBefore, 'total_after_minor' => $totalAfter, 'total_delta_minor' => $totalAfter - $totalBefore],
            ])->save();
            return $status === MarketplaceBulkState::READY ? $this->issueApproval($batch->fresh(), $userId) : ['batch' => $batch->fresh(), 'approval_token' => null];
        });
    }

    public function approve(MarketplaceBulkBatch $batch, string $token, int $userId): MarketplaceBulkBatch
    {
        return DB::transaction(function () use ($batch, $token, $userId): MarketplaceBulkBatch {
            $locked = MarketplaceBulkBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === MarketplaceBulkState::APPROVED) return $locked;
            if ((string) $locked->status !== MarketplaceBulkState::READY || (int) $locked->owner_user_id !== $userId) {
                throw ValidationException::withMessages(['batch' => 'Only an owned, ready bulk batch can be approved.']);
            }
            if ($locked->approval_expires_at === null || $locked->approval_expires_at->isPast() || ! hash_equals((string) $locked->approval_token_hash, hash('sha256', $token))) {
                throw ValidationException::withMessages(['approval_token' => 'The bulk approval token is invalid or expired.']);
            }
            try { $payload = MarketplaceWriteApprovalToken::verify($token, $this->secret()); }
            catch (Throwable $e) { throw ValidationException::withMessages(['approval_token' => $e->getMessage()]); }
            if ((string) $payload['proposal_uuid'] !== (string) $locked->uuid || (string) $payload['action_hash'] !== (string) $locked->batch_hash || (int) $payload['user_id'] !== $userId) {
                throw ValidationException::withMessages(['approval_token' => 'The bulk approval does not match this batch.']);
            }
            $locked->forceFill(['status' => MarketplaceBulkState::APPROVED, 'approved_by_user_id' => $userId, 'approved_at' => now(), 'approval_token_hash' => null])->save();
            return $locked->fresh();
        });
    }

    public function execute(MarketplaceBulkBatch $batch, int $userId): MarketplaceBulkBatch
    {
        $batch = DB::transaction(function () use ($batch, $userId): MarketplaceBulkBatch {
            $locked = MarketplaceBulkBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (! in_array((string) $locked->status, [MarketplaceBulkState::APPROVED, MarketplaceBulkState::PARTIALLY_COMPLETED, MarketplaceBulkState::FAILED], true) || (int) $locked->owner_user_id !== $userId) {
                throw ValidationException::withMessages(['batch' => 'This bulk batch is not approved for execution.']);
            }
            $locked->forceFill(['status' => MarketplaceBulkState::RUNNING, 'started_at' => $locked->started_at ?: now()])->save();
            return $locked->fresh();
        });

        foreach ($batch->items()->whereIn('status', ['ready', 'failed'])->orderBy('id')->get() as $item) {
            try {
                $proposal = MarketplaceWriteProposal::query()->findOrFail($item->proposal_id);
                $proposal = $this->writes->approveFromBatch($proposal, $userId, (string) $batch->uuid, (string) $batch->batch_hash);
                $proposal = $this->writes->execute($proposal);
                $status = (string) $proposal->status === MarketplaceWriteState::EXECUTED ? 'succeeded' : 'blocked';
                $item->forceFill(['status' => $status, 'executed_at' => $status === 'succeeded' ? now() : null, 'conflicts' => $proposal->conflict_state])->save();
            } catch (Throwable $e) {
                $item->forceFill(['status' => 'failed', 'error_hash' => hash('sha256', $e->getMessage())])->save();
            }
        }
        return $this->refreshCounts($batch);
    }

    public function rollback(MarketplaceBulkBatch $batch, int $userId): MarketplaceBulkBatch
    {
        if ((int) $batch->owner_user_id !== $userId) throw ValidationException::withMessages(['batch' => 'Only the owning seller can roll back this batch.']);
        foreach ($batch->items()->where('status', 'succeeded')->orderByDesc('id')->get() as $item) {
            try {
                $proposal = MarketplaceWriteProposal::query()->findOrFail($item->proposal_id);
                $proposal = $this->writes->rollback($proposal, $userId);
                if ((string) $proposal->status === MarketplaceWriteState::ROLLED_BACK) {
                    $item->forceFill(['status' => 'rolled_back', 'rolled_back_at' => now()])->save();
                }
            } catch (Throwable $e) {
                $item->forceFill(['status' => 'rollback_failed', 'error_hash' => hash('sha256', $e->getMessage())])->save();
            }
        }
        return $this->refreshCounts($batch, true);
    }

    /** @return array{batch:MarketplaceBulkBatch,approval_token:string} */
    private function issueApproval(MarketplaceBulkBatch $batch, int $userId): array
    {
        if ((string) $batch->status !== MarketplaceBulkState::READY) return ['batch' => $batch, 'approval_token' => ''];
        $ttl = max(60, (int) config('chatbot-ecommerce.marketplaces.write.bulk.approval_ttl_minutes', 15) * 60);
        $token = MarketplaceWriteApprovalToken::issue(['proposal_uuid' => $batch->uuid, 'action_hash' => $batch->batch_hash, 'chatbot_id' => (int) $batch->chatbot_id, 'user_id' => $userId], $this->secret(), $ttl);
        $batch->forceFill(['approval_token_hash' => hash('sha256', $token), 'approval_expires_at' => now()->addSeconds($ttl)])->save();
        return ['batch' => $batch->fresh(), 'approval_token' => $token];
    }

    /** @param array<string,mixed> $snapshot @param array<string,mixed> $template @return array<string,mixed> */
    private function changesFor(string $operation, array $snapshot, array $template): array
    {
        if ($operation === MarketplaceWriteOperation::UPDATE_PRICE) {
            $impact = MarketplaceBulkImpact::priceChange((int) ($snapshot['price_amount'] ?? 0), $template);
            return ['price_amount' => $impact['after'], 'currency' => $snapshot['currency'] ?? null];
        }
        if ($operation === MarketplaceWriteOperation::UPDATE_INVENTORY && isset($template['delta'])) {
            return ['quantity' => max(0, (int) ($snapshot['quantity'] ?? 0) + (int) $template['delta'])];
        }
        return $template;
    }

    /** @param array<string,mixed> $snapshot @param array<string,mixed> $changes @return array<string,mixed> */
    private function impactFor(string $operation, array $snapshot, array $changes): array
    {
        if ($operation === MarketplaceWriteOperation::UPDATE_PRICE) return MarketplaceBulkImpact::priceChange((int) ($snapshot['price_amount'] ?? 0), ['mode' => 'set', 'value' => (int) ($changes['price_amount'] ?? 0)]);
        if ($operation === MarketplaceWriteOperation::UPDATE_INVENTORY) return ['before' => (int) ($snapshot['quantity'] ?? 0), 'after' => (int) ($changes['quantity'] ?? 0), 'delta' => (int) ($changes['quantity'] ?? 0) - (int) ($snapshot['quantity'] ?? 0)];
        return ['before' => 0, 'after' => 0, 'delta' => 0];
    }

    private function refreshCounts(MarketplaceBulkBatch $batch, bool $rollback = false): MarketplaceBulkBatch
    {
        $items = $batch->items()->get();
        $succeeded = $items->where('status', 'succeeded')->count();
        $failed = $items->whereIn('status', ['failed', 'blocked', 'rollback_failed'])->count();
        $rolledBack = $items->where('status', 'rolled_back')->count();
        $eligible = max(1, (int) $batch->eligible_count);
        $status = $rollback
            ? ($rolledBack >= $eligible ? MarketplaceBulkState::ROLLED_BACK : MarketplaceBulkState::PARTIALLY_ROLLED_BACK)
            : ($succeeded >= $eligible ? MarketplaceBulkState::COMPLETED : ($succeeded > 0 ? MarketplaceBulkState::PARTIALLY_COMPLETED : MarketplaceBulkState::FAILED));
        $batch->forceFill(['status' => $status, 'succeeded_count' => $succeeded, 'failed_count' => $failed, 'rolled_back_count' => $rolledBack, 'completed_at' => $rollback ? $batch->completed_at : now(), 'rolled_back_at' => $rollback ? now() : null])->save();
        return $batch->fresh('items.proposal');
    }

    private function assertConnection(Chatbot $chatbot, MarketplaceConnection $connection, int $userId): void
    {
        if ((int) $chatbot->getAttribute('user_id') !== $userId || (int) $connection->chatbot_id !== (int) $chatbot->getAttribute('id') || (int) $connection->owner_user_id !== $userId || ! $connection->write_enabled) {
            throw ValidationException::withMessages(['connection' => 'This marketplace connection is not enabled for owned seller writes.']);
        }
    }
    private function assertEnabled(): void
    {
        if (! (bool) config('chatbot-ecommerce.marketplaces.write.allow_bulk_writes', false)) throw ValidationException::withMessages(['marketplace' => 'Marketplace bulk writes are disabled.']);
    }
    private function secret(): string { return trim((string) config('chatbot-ecommerce.marketplaces.write.approval_secret', '')); }
}
