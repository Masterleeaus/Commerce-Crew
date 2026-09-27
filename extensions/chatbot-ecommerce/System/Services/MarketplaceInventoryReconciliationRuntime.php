<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryItem;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryLocationStock;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryConflict;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryConflictEvent;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryMapping;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryPolicy;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryScanRun;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceListingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryChannelAllocator;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryConflictResolutionPolicy;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceInventoryReconciliation;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MarketplaceInventoryReconciliationRuntime
{
    public function __construct(
        private readonly MarketplaceProviderRegistry $readProviders,
        private readonly MarketplaceWriteRuntime $writes,
    ) {}

    public function policyFor(Chatbot $chatbot, MarketplaceConnection $connection): MarketplaceInventoryPolicy
    {
        $policy = MarketplaceInventoryPolicy::query()
            ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
            ->where('active', true)
            ->where(function ($query) use ($connection): void {
                $query->where('connection_id', $connection->id)->orWhereNull('connection_id');
            })
            ->orderByRaw('CASE WHEN connection_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        if ($policy !== null) {
            return $policy;
        }

        return new MarketplaceInventoryPolicy([
            'chatbot_id' => (int) $chatbot->getAttribute('id'),
            'connection_id' => $connection->id,
            'owner_user_id' => (int) $connection->owner_user_id,
            'source_of_truth' => 'internal',
            'resolution_mode' => 'suggest_only',
            'allocation_mode' => 'equal_share',
            'allocation_bps' => 10000,
            'buffer_quantity' => 0,
            'minimum_quantity' => 0,
            'maximum_quantity' => null,
            'maximum_sync_age_seconds' => (int) config('chatbot-ecommerce.marketplaces.inventory_reconciliation.maximum_sync_age_seconds', 300),
            'quantity_tolerance' => 0,
            'maximum_auto_delta' => 0,
            'require_fresh_snapshot' => true,
            'auto_map_by_sku' => true,
            'active' => true,
        ]);
    }

    public function discoverMappings(Chatbot $chatbot, MarketplaceConnection $connection): int
    {
        $policy = $this->policyFor($chatbot, $connection);
        if (! (bool) $policy->auto_map_by_sku) {
            return 0;
        }

        $created = 0;
        $snapshots = MarketplaceListingSnapshot::query()->where('connection_id', $connection->id)->get();
        foreach ($snapshots as $snapshot) {
            if (MarketplaceInventoryMapping::query()->where('connection_id', $connection->id)->where('external_listing_id', $snapshot->external_listing_id)->exists()) {
                continue;
            }
            $sku = $this->extractSku((array) $snapshot->snapshot);
            if ($sku === '') {
                continue;
            }
            $variants = ProductVariant::query()->where('sku', $sku)->limit(2)->get();
            if ($variants->count() !== 1) {
                continue;
            }
            MarketplaceInventoryMapping::query()->create([
                'chatbot_id' => (int) $chatbot->getAttribute('id'),
                'connection_id' => $connection->id,
                'listing_snapshot_id' => $snapshot->id,
                'variant_id' => $variants->first()->id,
                'external_listing_id' => $snapshot->external_listing_id,
                'sku' => $sku,
                'active' => true,
                'verified' => false,
                'metadata' => ['mapping_source' => 'exact_sku', 'requires_seller_verification' => true],
            ]);
            $created++;
        }

        return $created;
    }

    public function scan(Chatbot $chatbot, MarketplaceConnection $connection, ?int $actorUserId = null): MarketplaceInventoryScanRun
    {
        $this->assertConnection($chatbot, $connection);
        $run = MarketplaceInventoryScanRun::query()->create([
            'chatbot_id' => (int) $chatbot->getAttribute('id'),
            'connection_id' => $connection->id,
            'owner_user_id' => $actorUserId,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $autoMapped = $this->discoverMappings($chatbot, $connection);
            $mappings = MarketplaceInventoryMapping::query()
                ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
                ->where('connection_id', $connection->id)
                ->where('active', true)
                ->orderBy('id')
                ->get();

            $counts = ['in_sync'=>0, 'conflict'=>0, 'critical'=>0, 'prepared'=>0];
            foreach ($mappings as $mapping) {
                $result = $this->scanMapping($chatbot, $connection, $mapping, $run, $actorUserId);
                $counts[$result['conflict'] ? 'conflict' : 'in_sync']++;
                if (($result['severity'] ?? '') === 'critical') $counts['critical']++;
                if (($result['correction_prepared'] ?? false) === true) $counts['prepared']++;
            }

            $run->forceFill([
                'status' => 'completed',
                'mapping_count' => $mappings->count(),
                'in_sync_count' => $counts['in_sync'],
                'conflict_count' => $counts['conflict'],
                'critical_count' => $counts['critical'],
                'corrections_prepared' => $counts['prepared'],
                'completed_at' => now(),
                'metadata' => ['auto_mapped' => $autoMapped],
            ])->save();

            event(new ExtensionEvent('commerce.marketplace.inventory.scan.completed', ['scan_uuid'=>$run->uuid] + $counts));
            Metrics::increment('commerce.marketplace.inventory.scan.completed', ['provider'=>(string)$connection->provider]);
            return $run->fresh('conflicts.events');
        } catch (Throwable $e) {
            $run->forceFill(['status'=>'failed','failure_hash'=>hash('sha256', $e->getMessage()),'completed_at'=>now()])->save();
            Metrics::increment('commerce.marketplace.inventory.scan.failed', ['provider'=>(string)$connection->provider]);
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    public function scanMapping(Chatbot $chatbot, MarketplaceConnection $connection, MarketplaceInventoryMapping $mapping, ?MarketplaceInventoryScanRun $run = null, ?int $actorUserId = null, bool $allowAutoPrepare = true): array
    {
        $this->assertMapping($chatbot, $connection, $mapping);
        $policy = $this->policyFor($chatbot, $connection);
        $listing = $this->refreshListing($connection, $mapping);
        $canonical = $this->canonicalState($mapping);
        $allocation = InventoryChannelAllocator::allocate((int) $canonical['available'], $this->allocationPolicy($policy, $mapping));
        $external = [
            'quantity' => $this->extractQuantity((array) $listing->snapshot),
            'observed_at' => $listing->last_seen_at?->toIso8601String(),
            'source_hash' => (string) $listing->source_hash,
        ];
        $assessment = MarketplaceInventoryReconciliation::assess($canonical, $external, [
            'target_quantity' => $allocation['target_quantity'],
            'maximum_sync_age_seconds' => (int) $policy->maximum_sync_age_seconds,
            'quantity_tolerance' => (int) $policy->quantity_tolerance,
        ]);
        $assessment['allocation'] = $allocation;
        $assessment['source_of_truth'] = (string) $policy->source_of_truth;
        $assessment['mapping_verified'] = (bool) $mapping->verified;
        if (! (bool) $mapping->verified) {
            $assessment['blocking'] = true;
            $assessment['warnings'][] = ['code'=>'mapping_unverified','message'=>'The SKU mapping must be verified by the seller before correction.'];
        }

        $mapping->forceFill([
            'listing_snapshot_id' => $listing->id,
            'last_external_quantity' => (int) $external['quantity'],
            'last_target_quantity' => (int) $allocation['target_quantity'],
            'last_source_hash' => (string) $listing->source_hash,
            'last_scanned_at' => now(),
            'last_in_sync_at' => ! $assessment['conflict'] ? now() : $mapping->last_in_sync_at,
        ])->save();

        if (! $assessment['conflict']) {
            $this->resolveOpenConflicts($mapping, $actorUserId, 'inventory_returned_to_sync');
            return $assessment + ['correction_prepared'=>false];
        }

        $conflict = $this->recordConflict($mapping, $run, $assessment, $actorUserId);
        $eligibility = InventoryConflictResolutionPolicy::evaluate($policy->toArray(), $assessment);
        $prepared = false;
        if ($allowAutoPrepare && (string) $conflict->status !== 'ignored' && $eligibility['auto_prepare'] && (bool) $mapping->verified && (string) $policy->source_of_truth === 'internal') {
            $result = $this->prepareCorrection($chatbot, $conflict, (int) $connection->owner_user_id, 'automatic_policy');
            $prepared = $result['proposal'] !== null;
        }

        return $assessment + ['conflict_uuid'=>$conflict->uuid,'correction_prepared'=>$prepared,'resolution_eligibility'=>$eligibility];
    }

    /** @return array{conflict:MarketplaceInventoryConflict,proposal:mixed,approval_token:?string} */
    public function prepareCorrection(Chatbot $chatbot, MarketplaceInventoryConflict $conflict, int $userId, string $mode = 'manual'): array
    {
        $conflict->loadMissing('mapping.connection');
        $mapping = $conflict->mapping;
        $connection = $mapping->connection;
        $this->assertMapping($chatbot, $connection, $mapping);
        if ((int) $connection->owner_user_id !== $userId) {
            throw ValidationException::withMessages(['conflict'=>'Only the owning seller can prepare this correction.']);
        }
        if (! (bool) $mapping->verified) {
            throw ValidationException::withMessages(['mapping'=>'Verify the marketplace-to-variant mapping before preparing a correction.']);
        }
        $policy = $this->policyFor($chatbot, $connection);
        if ((string) $policy->source_of_truth !== 'internal') {
            throw ValidationException::withMessages(['policy'=>'This connection does not use internal inventory as its authoritative source, so an external stock correction cannot be prepared.']);
        }
        $latest = $this->scanMapping($chatbot, $connection, $mapping, null, $userId, false);
        if (($latest['code'] ?? '') === 'in_sync') {
            return ['conflict'=>$conflict->fresh(),'proposal'=>null,'approval_token'=>null];
        }
        if (($latest['code'] ?? '') === 'external_state_stale') {
            throw ValidationException::withMessages(['listing'=>'Refresh the marketplace listing before preparing an inventory correction.']);
        }

        $conflict = MarketplaceInventoryConflict::query()->where('mapping_id', $mapping->id)->whereIn('status', ['open','acknowledged','resolution_prepared'])->latest('id')->firstOrFail();
        if ($conflict->write_proposal_id !== null) {
            return ['conflict'=>$conflict,'proposal'=>$conflict->writeProposal,'approval_token'=>null];
        }
        $result = $this->writes->prepare(
            $chatbot,
            $connection,
            (string) $mapping->external_listing_id,
            'update_inventory',
            ['quantity'=>(int) $conflict->target_quantity],
            (string) $conflict->source_hash,
            'inventory-conflict:'.$conflict->uuid.':'.$conflict->source_hash.':'.$conflict->target_quantity,
            $userId
        );
        $proposal = $result['proposal'];
        $before = (string) $conflict->status;
        $conflict->forceFill([
            'write_proposal_id'=>$proposal->id,
            'status'=>'resolution_prepared',
            'resolution_mode'=>$mode,
            'metadata'=>array_replace_recursive((array)$conflict->metadata, ['write_status'=>$proposal->status]),
        ])->save();
        $this->event($conflict, 'correction_prepared', $before, 'resolution_prepared', $userId, ['proposal_uuid'=>$proposal->uuid,'mode'=>$mode]);
        event(new ExtensionEvent('commerce.marketplace.inventory.correction.prepared', ['conflict_uuid'=>$conflict->uuid,'proposal_uuid'=>$proposal->uuid]));

        return ['conflict'=>$conflict->fresh('writeProposal'),'proposal'=>$proposal,'approval_token'=>$result['approval_token']];
    }

    public function acknowledge(MarketplaceInventoryConflict $conflict, int $userId): MarketplaceInventoryConflict
    {
        return DB::transaction(function () use ($conflict, $userId): MarketplaceInventoryConflict {
            $locked = MarketplaceInventoryConflict::query()->whereKey($conflict->id)->lockForUpdate()->firstOrFail();
            $before = (string) $locked->status;
            if (in_array($before, ['resolved','ignored'], true)) return $locked;
            $locked->forceFill(['status'=>'acknowledged','acknowledged_by_user_id'=>$userId,'acknowledged_at'=>now()])->save();
            $this->event($locked, 'acknowledged', $before, 'acknowledged', $userId);
            return $locked->fresh('events');
        });
    }

    public function ignore(MarketplaceInventoryConflict $conflict, int $userId, string $reason, ?int $hours = null): MarketplaceInventoryConflict
    {
        $reason = trim($reason);
        if ($reason === '') throw ValidationException::withMessages(['reason'=>'An audit reason is required.']);
        return DB::transaction(function () use ($conflict, $userId, $reason, $hours): MarketplaceInventoryConflict {
            $locked = MarketplaceInventoryConflict::query()->whereKey($conflict->id)->lockForUpdate()->firstOrFail();
            $before = (string) $locked->status;
            $locked->forceFill([
                'status'=>'ignored','ignored_by_user_id'=>$userId,'ignored_at'=>now(),
                'ignored_until'=>$hours === null ? null : now()->addHours(min(720, max(1, $hours))),
                'ignore_reason'=>$reason,
            ])->save();
            $this->event($locked, 'ignored', $before, 'ignored', $userId, ['reason'=>$reason,'ignored_until'=>$locked->ignored_until?->toIso8601String()]);
            return $locked->fresh('events');
        });
    }

    /** @return array<string,mixed> */
    public function canonicalState(MarketplaceInventoryMapping $mapping): array
    {
        ProductVariant::query()->findOrFail($mapping->variant_id);
        if ($mapping->location_id !== null) {
            $row = InventoryLocationStock::query()->where('variant_id', $mapping->variant_id)->where('location_id', $mapping->location_id)->first();
            return $row === null ? $this->emptyCanonical() : $this->canonicalFromRows([$row]);
        }
        $rows = InventoryLocationStock::query()->where('variant_id', $mapping->variant_id)->get();
        if ($rows->isNotEmpty()) return $this->canonicalFromRows($rows->all());
        $item = InventoryItem::query()->where('variant_id', $mapping->variant_id)->first();
        return $item === null ? $this->emptyCanonical() : $this->canonicalFromRows([$item]);
    }

    /** @param array<int,object> $rows @return array<string,int> */
    private function canonicalFromRows(array $rows): array
    {
        $state = $this->emptyCanonical();
        foreach ($rows as $row) {
            foreach (['quantity','reserved','committed','incoming','damaged','safety_stock'] as $field) $state[$field] += (int) $row->{$field};
            $state['available'] += (int) $row->available;
            $state['version'] += (int) $row->version;
        }
        return $state;
    }

    /** @return array<string,int> */
    private function emptyCanonical(): array
    {
        return ['quantity'=>0,'reserved'=>0,'committed'=>0,'incoming'=>0,'damaged'=>0,'safety_stock'=>0,'available'=>0,'version'=>0];
    }

    private function refreshListing(MarketplaceConnection $connection, MarketplaceInventoryMapping $mapping): MarketplaceListingSnapshot
    {
        $snapshot = MarketplaceListingSnapshot::query()->where('connection_id', $connection->id)->where('external_listing_id', $mapping->external_listing_id)->first();
        try {
            $live = $this->readProviders->get((string) $connection->provider)->getListing($connection, (string) $mapping->external_listing_id);
            if ($live !== null) {
                $normalized = (array) $live;
                $sourceHash = (string) ($normalized['source_hash'] ?? hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR)));
                $snapshot = MarketplaceListingSnapshot::query()->updateOrCreate(
                    ['connection_id'=>$connection->id,'external_listing_id'=>$mapping->external_listing_id],
                    ['chatbot_id'=>$connection->chatbot_id,'provider'=>$connection->provider,'source_hash'=>$sourceHash,'snapshot'=>$normalized,'first_seen_at'=>$snapshot?->first_seen_at ?? now(),'last_seen_at'=>now()]
                );
            }
        } catch (Throwable $e) {
            if ($snapshot === null) throw $e;
        }
        if ($snapshot === null) throw ValidationException::withMessages(['listing'=>'No marketplace listing observation exists for this mapping.']);
        return $snapshot;
    }

    /** @return array<string,mixed> */
    private function allocationPolicy(MarketplaceInventoryPolicy $policy, MarketplaceInventoryMapping $mapping): array
    {
        $channelCount = MarketplaceInventoryMapping::query()
            ->where('chatbot_id', $mapping->chatbot_id)
            ->where('variant_id', $mapping->variant_id)
            ->where('active', true)
            ->when($mapping->location_id === null, fn ($query) => $query->whereNull('location_id'), fn ($query) => $query->where('location_id', $mapping->location_id))
            ->count();
        return [
            'allocation_mode'=>$mapping->allocation_mode ?? $policy->allocation_mode,
            'allocation_bps'=>$mapping->allocation_bps ?? $policy->allocation_bps,
            'buffer_quantity'=>$mapping->buffer_quantity ?? $policy->buffer_quantity,
            'minimum_quantity'=>$mapping->minimum_quantity ?? $policy->minimum_quantity,
            'maximum_quantity'=>$mapping->maximum_quantity ?? $policy->maximum_quantity,
            'channel_count'=>max(1, $channelCount),
        ];
    }

    private function recordConflict(MarketplaceInventoryMapping $mapping, ?MarketplaceInventoryScanRun $run, array $assessment, ?int $actorUserId): MarketplaceInventoryConflict
    {
        return DB::transaction(function () use ($mapping, $run, $assessment, $actorUserId): MarketplaceInventoryConflict {
            MarketplaceInventoryConflict::query()->where('mapping_id', $mapping->id)->whereNotIn('status', ['resolved','ignored'])->where('code', '<>', $assessment['code'])->get()->each(function ($old) use ($actorUserId): void {
                $before = (string)$old->status;
                $old->forceFill(['status'=>'resolved','resolved_by_user_id'=>$actorUserId,'resolved_at'=>now(),'resolution_mode'=>'superseded'])->save();
                $this->event($old, 'superseded', $before, 'resolved', $actorUserId);
            });
            $conflict = MarketplaceInventoryConflict::query()->where('mapping_id', $mapping->id)->where('code', $assessment['code'])->whereIn('status', ['open','acknowledged','ignored','resolution_prepared'])->latest('id')->first();
            $now = now();
            $status = 'open';
            if ($conflict !== null && (string)$conflict->status === 'ignored' && ($conflict->ignored_until === null || $conflict->ignored_until->isFuture())) $status = 'ignored';
            elseif ($conflict !== null && (string)$conflict->status === 'resolution_prepared') $status = 'resolution_prepared';
            elseif ($conflict !== null && (string)$conflict->status === 'acknowledged') $status = 'acknowledged';
            $values = [
                'chatbot_id'=>$mapping->chatbot_id,'connection_id'=>$mapping->connection_id,'mapping_id'=>$mapping->id,'scan_run_id'=>$run?->id,
                'provider'=>$mapping->connection->provider,'external_listing_id'=>$mapping->external_listing_id,'code'=>$assessment['code'],
                'severity'=>$assessment['severity'],'status'=>$status,'canonical_quantity'=>$assessment['canonical_available'],'target_quantity'=>$assessment['target_quantity'],
                'external_quantity'=>$assessment['external_quantity'],'delta'=>$assessment['delta'],'oversell_exposure'=>(int)($assessment['oversell_exposure'] ?? 0),
                'canonical_version'=>$assessment['canonical_version'],'source_hash'=>$assessment['source_hash'],'observed_at'=>$mapping->listing?->last_seen_at ?? $now,
                'first_detected_at'=>$conflict?->first_detected_at ?? $now,'last_detected_at'=>$now,'assessment'=>$assessment,
            ];
            if ($conflict === null) {
                $conflict = MarketplaceInventoryConflict::query()->create($values);
                $this->event($conflict, 'detected', null, $status, $actorUserId, ['assessment'=>$assessment]);
            } else {
                $before = (string)$conflict->status;
                $conflict->forceFill($values)->save();
                $this->event($conflict, 'observed_again', $before, $status, $actorUserId, ['assessment_hash'=>hash('sha256', json_encode($assessment, JSON_THROW_ON_ERROR))]);
            }
            event(new ExtensionEvent('commerce.marketplace.inventory.conflict.detected', ['conflict_uuid'=>$conflict->uuid,'code'=>$conflict->code,'severity'=>$conflict->severity]));
            Metrics::increment('commerce.marketplace.inventory.conflict.detected', ['provider'=>(string)$conflict->provider,'severity'=>(string)$conflict->severity]);
            return $conflict->fresh('events');
        });
    }

    private function resolveOpenConflicts(MarketplaceInventoryMapping $mapping, ?int $userId, string $mode): void
    {
        MarketplaceInventoryConflict::query()->where('mapping_id', $mapping->id)->whereNotIn('status', ['resolved','ignored'])->get()->each(function ($conflict) use ($userId, $mode): void {
            $before = (string)$conflict->status;
            $conflict->forceFill(['status'=>'resolved','resolved_by_user_id'=>$userId,'resolved_at'=>now(),'resolution_mode'=>$mode])->save();
            $this->event($conflict, 'resolved', $before, 'resolved', $userId, ['mode'=>$mode]);
        });
    }

    private function event(MarketplaceInventoryConflict $conflict, string $type, ?string $before, ?string $after, ?int $userId, array $payload = []): void
    {
        MarketplaceInventoryConflictEvent::query()->create([
            'conflict_id'=>$conflict->id,'event_type'=>$type,'actor_type'=>$userId === null ? 'system' : 'seller',
            'actor_user_id'=>$userId,'status_before'=>$before,'status_after'=>$after,'payload'=>$payload,'occurred_at'=>now(),
        ]);
    }

    private function assertConnection(Chatbot $chatbot, MarketplaceConnection $connection): void
    {
        if ((int)$connection->chatbot_id !== (int)$chatbot->getAttribute('id')) throw ValidationException::withMessages(['connection'=>'Marketplace connection does not belong to this storefront.']);
    }

    private function assertMapping(Chatbot $chatbot, MarketplaceConnection $connection, MarketplaceInventoryMapping $mapping): void
    {
        $this->assertConnection($chatbot, $connection);
        if ((int)$mapping->chatbot_id !== (int)$chatbot->getAttribute('id') || (int)$mapping->connection_id !== (int)$connection->id) throw ValidationException::withMessages(['mapping'=>'Inventory mapping does not belong to this marketplace connection.']);
    }

    private function extractSku(array $snapshot): string
    {
        $attributes = is_array($snapshot['attributes'] ?? null) ? $snapshot['attributes'] : [];
        return trim((string)($snapshot['sku'] ?? $attributes['sku'] ?? $attributes['seller_sku'] ?? ''));
    }

    private function extractQuantity(array $snapshot): int
    {
        $attributes = is_array($snapshot['attributes'] ?? null) ? $snapshot['attributes'] : [];
        foreach (['quantity','available_quantity','inventory_quantity','stock','quantity_available'] as $key) {
            if (array_key_exists($key, $snapshot)) return max(0, (int)$snapshot[$key]);
            if (array_key_exists($key, $attributes)) return max(0, (int)$attributes[$key]);
        }
        return 0;
    }
}
