<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceListingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceWriteAttempt;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceWriteProposal;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceCapability;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceConflictDetector;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteApprovalToken;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteOperation;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWritePatch;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceWriteState;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MarketplaceWriteRuntime
{
    public function __construct(
        private readonly MarketplaceWriteProviderRegistry $writeProviders,
        private readonly MarketplaceProviderRegistry $readProviders,
        private readonly MarketplaceInventoryConflictRuntime $inventoryConflicts,
        private readonly MarketplaceRateLimitRuntime $rateLimits,
    ) {}

    /**
     * @param array<string,mixed> $changes
     * @return array{proposal:MarketplaceWriteProposal,approval_token:?string}
     */
    public function prepare(
        Chatbot $chatbot,
        MarketplaceConnection $connection,
        string $externalListingId,
        string $operation,
        array $changes,
        string $expectedSourceHash,
        string $idempotencyKey,
        int $userId
    ): array {
        $this->assertEnabled();
        $this->assertConnection($chatbot, $connection, $userId);
        $operation = MarketplaceWriteOperation::capability($operation);
        $this->assertCapability($connection, $operation);
        $changes = MarketplaceWritePatch::sanitize($operation, $changes);
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'A bounded idempotency key is required.']);
        }

        $actionHash = hash('sha256', json_encode([
            'chatbot_id' => (int) $chatbot->getAttribute('id'),
            'connection_uuid' => $connection->uuid,
            'external_listing_id' => $externalListingId,
            'operation' => $operation,
            'changes_hash' => MarketplaceWritePatch::hash($operation, $changes),
            'expected_source_hash' => $expectedSourceHash,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $existing = MarketplaceWriteProposal::query()
            ->where('connection_id', $connection->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) {
            if (! hash_equals((string) $existing->action_hash, $actionHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different marketplace action.']);
            }
            return $this->renewApprovalToken($existing, $userId);
        }

        $listing = MarketplaceListingSnapshot::query()
            ->where('connection_id', $connection->id)
            ->where('external_listing_id', $externalListingId)
            ->first();
        if ($listing === null) {
            throw ValidationException::withMessages(['listing' => 'Refresh this marketplace listing before preparing a write.']);
        }

        $internalAvailable = $operation === MarketplaceWriteOperation::UPDATE_INVENTORY
            ? $this->inventoryConflicts->availableForListing($listing)
            : null;
        $conflictState = MarketplaceConflictDetector::assess(
            ['source_hash' => $listing->source_hash, 'last_seen_at' => $listing->last_seen_at, 'snapshot' => $listing->snapshot],
            $expectedSourceHash,
            $operation,
            $changes,
            [
                'maximum_snapshot_age_seconds' => (int) config('chatbot-ecommerce.marketplaces.write.maximum_snapshot_age_seconds', 300),
                'internal_available' => $internalAvailable,
                'allow_oversell' => (bool) config('chatbot-ecommerce.marketplaces.write.allow_inventory_oversell', false),
            ]
        );
        $before = $this->stateForOperation((array) $listing->snapshot, $operation);
        $proposal = MarketplaceWriteProposal::query()->create([
            'chatbot_id' => (int) $chatbot->getAttribute('id'),
            'connection_id' => $connection->id,
            'owner_user_id' => $userId,
            'provider' => $connection->provider,
            'external_listing_id' => $externalListingId,
            'operation' => $operation,
            'status' => $conflictState['blocking'] ? MarketplaceWriteState::CONFLICT_BLOCKED : MarketplaceWriteState::PREPARED,
            'idempotency_key' => $idempotencyKey,
            'action_hash' => $actionHash,
            'expected_source_hash' => $expectedSourceHash,
            'requested_changes' => $changes,
            'before_state' => $before,
            'rollback_state' => $before,
            'conflict_state' => $conflictState,
            'metadata' => ['internal_available' => $internalAvailable],
        ]);

        if ($conflictState['blocking']) {
            event(new ExtensionEvent('commerce.marketplace.write.blocked', ['proposal_uuid' => $proposal->uuid, 'conflicts' => $conflictState['conflicts']]));
            Metrics::increment('commerce.marketplace.write.blocked', ['provider' => (string) $connection->provider, 'operation' => $operation]);
            return ['proposal' => $proposal, 'approval_token' => null];
        }

        if (! (bool) config('chatbot-ecommerce.marketplaces.write.require_approval', true)) {
            $proposal->forceFill([
                'status' => MarketplaceWriteState::APPROVED,
                'approved_by_user_id' => $userId,
                'approved_at' => now(),
                'approval_snapshot' => ['mode' => 'configuration', 'action_hash' => $actionHash],
            ])->save();
            return ['proposal' => $proposal->fresh(), 'approval_token' => null];
        }

        return $this->renewApprovalToken($proposal, $userId);
    }

    /** @return array{proposal:MarketplaceWriteProposal,approval_token:string} */
    private function renewApprovalToken(MarketplaceWriteProposal $proposal, int $userId): array
    {
        if ((string) $proposal->status !== MarketplaceWriteState::PREPARED) {
            return ['proposal' => $proposal, 'approval_token' => ''];
        }
        $ttlSeconds = max(60, (int) config('chatbot-ecommerce.marketplaces.write.approval_ttl_minutes', 10) * 60);
        $token = MarketplaceWriteApprovalToken::issue([
            'proposal_uuid' => $proposal->uuid,
            'action_hash' => $proposal->action_hash,
            'chatbot_id' => (int) $proposal->chatbot_id,
            'user_id' => $userId,
        ], $this->approvalSecret(), $ttlSeconds);
        $proposal->forceFill([
            'approval_token_hash' => hash('sha256', $token),
            'approval_expires_at' => now()->addSeconds($ttlSeconds),
        ])->save();

        return ['proposal' => $proposal->fresh(), 'approval_token' => $token];
    }

    public function approve(MarketplaceWriteProposal $proposal, string $token, int $userId): MarketplaceWriteProposal
    {
        return DB::transaction(function () use ($proposal, $token, $userId): MarketplaceWriteProposal {
            $locked = MarketplaceWriteProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === MarketplaceWriteState::APPROVED) {
                return $locked;
            }
            if ((string) $locked->status !== MarketplaceWriteState::PREPARED) {
                throw ValidationException::withMessages(['proposal' => 'Only a conflict-free prepared write can be approved.']);
            }
            if ($locked->approval_expires_at === null || $locked->approval_expires_at->isPast()) {
                throw ValidationException::withMessages(['approval_token' => 'The marketplace approval has expired. Prepare the action again.']);
            }
            if (! hash_equals((string) $locked->approval_token_hash, hash('sha256', $token))) {
                throw ValidationException::withMessages(['approval_token' => 'The marketplace approval token is invalid.']);
            }
            try {
                $payload = MarketplaceWriteApprovalToken::verify($token, $this->approvalSecret());
            } catch (Throwable $e) {
                throw ValidationException::withMessages(['approval_token' => $e->getMessage()]);
            }
            if ((string) $payload['proposal_uuid'] !== (string) $locked->uuid
                || (string) $payload['action_hash'] !== (string) $locked->action_hash
                || (int) $payload['chatbot_id'] !== (int) $locked->chatbot_id
                || (int) $payload['user_id'] !== $userId
                || (int) $locked->owner_user_id !== $userId) {
                throw ValidationException::withMessages(['approval_token' => 'The marketplace approval token does not match this action.']);
            }

            $locked->forceFill([
                'status' => MarketplaceWriteState::APPROVED,
                'approved_by_user_id' => $userId,
                'approved_at' => now(),
                'approval_snapshot' => [
                    'jti_hash' => hash('sha256', (string) $payload['jti']),
                    'action_hash' => $locked->action_hash,
                    'expected_source_hash' => $locked->expected_source_hash,
                ],
                'approval_token_hash' => null,
            ])->save();

            event(new ExtensionEvent('commerce.marketplace.write.approved', ['proposal_uuid' => $locked->uuid]));
            Metrics::increment('commerce.marketplace.write.approved', ['provider' => (string) $locked->provider, 'operation' => (string) $locked->operation]);

            return $locked->fresh();
        });
    }

    public function approveFromBatch(MarketplaceWriteProposal $proposal, int $userId, string $batchUuid, string $batchHash): MarketplaceWriteProposal
    {
        return DB::transaction(function () use ($proposal, $userId, $batchUuid, $batchHash): MarketplaceWriteProposal {
            $locked = MarketplaceWriteProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === MarketplaceWriteState::APPROVED) {
                return $locked;
            }
            if ((string) $locked->status !== MarketplaceWriteState::PREPARED || (int) $locked->owner_user_id !== $userId) {
                throw ValidationException::withMessages(['proposal' => 'Only an owned, prepared marketplace write can be approved by a batch.']);
            }
            $locked->forceFill([
                'status' => MarketplaceWriteState::APPROVED,
                'approved_by_user_id' => $userId,
                'approved_at' => now(),
                'approval_snapshot' => [
                    'mode' => 'bulk_batch',
                    'batch_uuid' => $batchUuid,
                    'batch_hash' => $batchHash,
                    'action_hash' => $locked->action_hash,
                    'expected_source_hash' => $locked->expected_source_hash,
                ],
                'approval_token_hash' => null,
            ])->save();

            return $locked->fresh();
        });
    }

    public function markQueued(MarketplaceWriteProposal $proposal): MarketplaceWriteProposal
    {
        return DB::transaction(function () use ($proposal): MarketplaceWriteProposal {
            $locked = MarketplaceWriteProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== MarketplaceWriteState::APPROVED) {
                throw ValidationException::withMessages(['proposal' => 'Only an approved marketplace write can be queued.']);
            }
            $locked->forceFill(['status' => MarketplaceWriteState::QUEUED, 'queued_at' => now()])->save();
            return $locked->fresh();
        });
    }

    public function execute(MarketplaceWriteProposal $proposal): MarketplaceWriteProposal
    {
        $attempt = null;
        try {
            $locked = DB::transaction(function () use ($proposal, &$attempt): MarketplaceWriteProposal {
                $locked = MarketplaceWriteProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
                if ((string) $locked->status === MarketplaceWriteState::EXECUTED) {
                    return $locked;
                }
                if (! in_array((string) $locked->status, [MarketplaceWriteState::APPROVED, MarketplaceWriteState::QUEUED, MarketplaceWriteState::FAILED], true)) {
                    throw ValidationException::withMessages(['proposal' => 'Marketplace write must be approved before execution.']);
                }
                $connection = MarketplaceConnection::query()->whereKey($locked->connection_id)->lockForUpdate()->firstOrFail();
                $this->assertCapability($connection, (string) $locked->operation);

                $authoritative = $this->readProviders->get((string) $connection->provider)->getListing($connection, (string) $locked->external_listing_id);
                if ($authoritative === null) {
                    throw ValidationException::withMessages(['listing' => 'The marketplace listing could not be refreshed before execution.']);
                }
                $snapshot = $this->storeListingSnapshot($locked, $authoritative);
                $internalAvailable = (string) $locked->operation === MarketplaceWriteOperation::UPDATE_INVENTORY
                    ? $this->inventoryConflicts->availableForListing($snapshot)
                    : null;
                $conflicts = MarketplaceConflictDetector::assess(
                    ['source_hash' => $snapshot->source_hash, 'last_seen_at' => $snapshot->last_seen_at, 'snapshot' => $snapshot->snapshot],
                    (string) $locked->expected_source_hash,
                    (string) $locked->operation,
                    (array) $locked->requested_changes,
                    [
                        'maximum_snapshot_age_seconds' => (int) config('chatbot-ecommerce.marketplaces.write.maximum_snapshot_age_seconds', 300),
                        'internal_available' => $internalAvailable,
                        'allow_oversell' => (bool) config('chatbot-ecommerce.marketplaces.write.allow_inventory_oversell', false),
                    ]
                );
                if ($conflicts['blocking']) {
                    $locked->forceFill(['status' => MarketplaceWriteState::CONFLICT_BLOCKED, 'conflict_state' => $conflicts])->save();
                    return $locked->fresh();
                }

                $this->rateLimits->consume($connection, (string) $locked->operation);
                $locked->forceFill(['status' => MarketplaceWriteState::EXECUTING])->save();
                $attempt = MarketplaceWriteAttempt::query()->create([
                    'proposal_id' => $locked->id,
                    'attempt_number' => MarketplaceWriteAttempt::query()->where('proposal_id', $locked->id)->where('phase', 'execute')->count() + 1,
                    'phase' => 'execute',
                    'status' => 'started',
                    'request_hash' => hash('sha256', json_encode($locked->requested_changes, JSON_THROW_ON_ERROR)),
                    'started_at' => now(),
                ]);

                return $locked->fresh();
            });

            if (in_array((string) $locked->status, [MarketplaceWriteState::EXECUTED, MarketplaceWriteState::CONFLICT_BLOCKED], true)) {
                return $locked;
            }
            $connection = $locked->connection()->firstOrFail();
            $result = $this->writeProviders->get((string) $connection->provider)->execute(
                $connection,
                (string) $locked->external_listing_id,
                (string) $locked->operation,
                (array) $locked->requested_changes,
                (string) $locked->expected_source_hash,
                (string) $locked->idempotency_key
            );
            $listing = (array) ($result['listing'] ?? []);

            $completed = DB::transaction(function () use ($locked, $attempt, $connection, $result, $listing): MarketplaceWriteProposal {
                $proposal = MarketplaceWriteProposal::query()->whereKey($locked->id)->lockForUpdate()->firstOrFail();
                $snapshot = $this->storeListingSnapshot($proposal, $listing);
                $proposal->forceFill([
                    'status' => MarketplaceWriteState::EXECUTED,
                    'after_state' => $this->stateForOperation((array) $snapshot->snapshot, (string) $proposal->operation),
                    'after_source_hash' => $snapshot->source_hash,
                    'provider_result' => [
                        'provider_request_id' => $result['provider_request_id'] ?? null,
                        'warnings' => $result['warnings'] ?? [],
                    ],
                    'executed_at' => now(),
                    'failed_at' => null,
                    'error_hash' => null,
                ])->save();
                if ($attempt !== null) {
                    $attempt->forceFill([
                        'status' => 'succeeded',
                        'response_hash' => hash('sha256', json_encode($listing, JSON_THROW_ON_ERROR)),
                        'provider_request_id' => $result['provider_request_id'] ?? null,
                        'completed_at' => now(),
                    ])->save();
                }
                $connection->forceFill(['last_write_at' => now(), 'last_error' => null])->save();
                $this->rateLimits->recordProviderState($connection, (string) $proposal->operation, (array) ($result['rate_limit'] ?? []));

                return $proposal->fresh();
            });

            event(new ExtensionEvent('commerce.marketplace.write.executed', ['proposal_uuid' => $completed->uuid, 'provider' => $completed->provider, 'operation' => $completed->operation]));
            Metrics::increment('commerce.marketplace.write.executed', ['provider' => (string) $completed->provider, 'operation' => (string) $completed->operation]);

            return $completed;
        } catch (Throwable $e) {
            if ($proposal->exists) {
                $connectionId = (int) ($proposal->connection_id ?? 0);
                if ($connectionId > 0) {
                    MarketplaceConnection::query()->whereKey($connectionId)->update(['last_error' => 'Write failed: ' . hash('sha256', $e->getMessage()), 'updated_at' => now()]);
                }
                MarketplaceWriteProposal::query()->whereKey($proposal->id)
                    ->whereNotIn('status', [MarketplaceWriteState::EXECUTED, MarketplaceWriteState::CONFLICT_BLOCKED])
                    ->update(['status' => MarketplaceWriteState::FAILED, 'failed_at' => now(), 'error_hash' => hash('sha256', $e->getMessage()), 'updated_at' => now()]);
            }
            if ($attempt !== null) {
                $attempt->forceFill(['status' => 'failed', 'error_hash' => hash('sha256', $e->getMessage()), 'completed_at' => now()])->save();
            }
            throw $e;
        }
    }

    public function rollback(MarketplaceWriteProposal $proposal, int $userId): MarketplaceWriteProposal
    {
        $attempt = null;
        try {
            $locked = DB::transaction(function () use ($proposal, $userId, &$attempt): MarketplaceWriteProposal {
                $locked = MarketplaceWriteProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
                if ((string) $locked->status === MarketplaceWriteState::ROLLED_BACK) {
                    return $locked;
                }
                if (! in_array((string) $locked->status, [MarketplaceWriteState::EXECUTED, MarketplaceWriteState::ROLLBACK_FAILED], true) || (int) $locked->owner_user_id !== $userId) {
                    throw ValidationException::withMessages(['proposal' => 'Only the owning seller can roll back an executed marketplace write.']);
                }
                $connection = MarketplaceConnection::query()->whereKey($locked->connection_id)->lockForUpdate()->firstOrFail();
                $authoritative = $this->readProviders->get((string) $connection->provider)->getListing($connection, (string) $locked->external_listing_id);
                if ($authoritative === null) {
                    throw ValidationException::withMessages(['listing' => 'The marketplace listing could not be refreshed before rollback.']);
                }
                $snapshot = $this->storeListingSnapshot($locked, $authoritative);
                if ((string) $locked->after_source_hash === '' || ! hash_equals((string) $locked->after_source_hash, (string) $snapshot->source_hash)) {
                    throw ValidationException::withMessages(['conflict' => 'The listing changed after this action. Review the newer state before rollback.']);
                }
                $this->rateLimits->consume($connection, (string) $locked->operation);
                $attempt = MarketplaceWriteAttempt::query()->create([
                    'proposal_id' => $locked->id,
                    'attempt_number' => MarketplaceWriteAttempt::query()->where('proposal_id', $locked->id)->where('phase', 'rollback')->count() + 1,
                    'phase' => 'rollback',
                    'status' => 'started',
                    'request_hash' => hash('sha256', json_encode($locked->rollback_state, JSON_THROW_ON_ERROR)),
                    'started_at' => now(),
                ]);

                return $locked;
            });

            if ((string) $locked->status === MarketplaceWriteState::ROLLED_BACK) {
                return $locked;
            }
            $connection = $locked->connection()->firstOrFail();
            $result = $this->writeProviders->get((string) $connection->provider)->rollback(
                $connection,
                (string) $locked->external_listing_id,
                (string) $locked->operation,
                (array) $locked->rollback_state,
                (string) $locked->after_source_hash,
                'rollback:' . $locked->idempotency_key
            );
            $listing = (array) ($result['listing'] ?? []);

            $completed = DB::transaction(function () use ($locked, $attempt, $connection, $result, $listing): MarketplaceWriteProposal {
                $proposal = MarketplaceWriteProposal::query()->whereKey($locked->id)->lockForUpdate()->firstOrFail();
                $snapshot = $this->storeListingSnapshot($proposal, $listing);
                $proposal->forceFill([
                    'status' => MarketplaceWriteState::ROLLED_BACK,
                    'rolled_back_at' => now(),
                    'metadata' => array_replace_recursive((array) $proposal->metadata, [
                        'rollback_result' => $this->stateForOperation((array) $snapshot->snapshot, (string) $proposal->operation),
                        'rollback_source_hash' => $snapshot->source_hash,
                    ]),
                ])->save();
                if ($attempt !== null) {
                    $attempt->forceFill([
                        'status' => 'succeeded',
                        'response_hash' => hash('sha256', json_encode($listing, JSON_THROW_ON_ERROR)),
                        'provider_request_id' => $result['provider_request_id'] ?? null,
                        'completed_at' => now(),
                    ])->save();
                }
                $connection->forceFill(['last_write_at' => now(), 'last_error' => null])->save();
                $this->rateLimits->recordProviderState($connection, (string) $proposal->operation, (array) ($result['rate_limit'] ?? []));

                return $proposal->fresh();
            });

            event(new ExtensionEvent('commerce.marketplace.write.rolled_back', ['proposal_uuid' => $completed->uuid]));
            Metrics::increment('commerce.marketplace.write.rolled_back', ['provider' => (string) $completed->provider, 'operation' => (string) $completed->operation]);

            return $completed;
        } catch (Throwable $e) {
            $connectionId = (int) ($proposal->connection_id ?? 0);
            if ($connectionId > 0) {
                MarketplaceConnection::query()->whereKey($connectionId)->update(['last_error' => 'Rollback failed: ' . hash('sha256', $e->getMessage()), 'updated_at' => now()]);
            }
            if ($attempt !== null) {
                $attempt->forceFill(['status' => 'failed', 'error_hash' => hash('sha256', $e->getMessage()), 'completed_at' => now()])->save();
            }
            MarketplaceWriteProposal::query()->whereKey($proposal->id)->where('status', MarketplaceWriteState::EXECUTED)
                ->update(['status' => MarketplaceWriteState::ROLLBACK_FAILED, 'error_hash' => hash('sha256', $e->getMessage()), 'updated_at' => now()]);
            throw $e;
        }
    }

    private function assertEnabled(): void
    {
        if ((string) config('chatbot-ecommerce.marketplaces.write.marketplace_write_mode', 'approval_only') === 'disabled') {
            throw ValidationException::withMessages(['marketplace' => 'Marketplace writes are disabled for this installation.']);
        }
        if ((bool) config('chatbot-ecommerce.marketplaces.write.require_approval', true) && $this->approvalSecret() === '') {
            throw ValidationException::withMessages(['marketplace' => 'Marketplace writes are unavailable until the approval signing secret is configured.']);
        }
        if ((bool) config('chatbot-ecommerce.marketplaces.write.allow_bulk_writes', false)) {
            // Bulk execution is deliberately not implemented in this pass. The flag is retained for future explicit enablement.
        }
    }

    private function assertConnection(Chatbot $chatbot, MarketplaceConnection $connection, int $userId): void
    {
        if (! $connection->active
            || (int) $connection->chatbot_id !== (int) $chatbot->getAttribute('id')
            || (int) $connection->owner_user_id !== $userId
            || (int) $chatbot->getAttribute('user_id') !== $userId) {
            throw ValidationException::withMessages(['marketplace' => 'The marketplace connection is not available to this seller.']);
        }
    }

    private function assertCapability(MarketplaceConnection $connection, string $operation): void
    {
        $allowedOperations = (array) config('chatbot-ecommerce.marketplaces.write.allowed_operations', []);
        if (! (bool) ($connection->write_enabled ?? false)
            || ! MarketplaceCapability::isWriteAllowed($operation)
            || ($allowedOperations !== [] && ! in_array($operation, $allowedOperations, true))
            || ! in_array($operation, (array) $connection->capabilities, true)) {
            throw ValidationException::withMessages(['operation' => 'This marketplace connection does not advertise the requested write capability.']);
        }
    }

    private function approvalSecret(): string
    {
        return (string) config('chatbot-ecommerce.marketplaces.write.approval_secret', '');
    }

    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    private function stateForOperation(array $snapshot, string $operation): array
    {
        $attributes = is_array($snapshot['attributes'] ?? null) ? $snapshot['attributes'] : [];

        return match ($operation) {
            MarketplaceWriteOperation::UPDATE_PRICE => [
                'price_amount' => (int) ($snapshot['price_amount'] ?? 0),
                'currency' => strtoupper((string) ($snapshot['currency'] ?? 'USD')),
            ],
            MarketplaceWriteOperation::UPDATE_INVENTORY => [
                'quantity' => max(0, (int) ($attributes['quantity'] ?? $attributes['inventory_quantity'] ?? 0)),
            ],
            MarketplaceWriteOperation::UPDATE_LISTING => [
                'title' => (string) ($snapshot['title'] ?? ''),
                'description' => (string) ($snapshot['description'] ?? ''),
                'attributes' => $attributes,
            ],
            MarketplaceWriteOperation::PAUSE_LISTING,
            MarketplaceWriteOperation::RESUME_LISTING => [
                'availability' => (string) ($snapshot['availability'] ?? 'unknown'),
                'status' => (string) ($attributes['status'] ?? ''),
            ],
            default => [],
        };
    }

    /** @param array<string,mixed> $listing */
    private function storeListingSnapshot(MarketplaceWriteProposal $proposal, array $listing): MarketplaceListingSnapshot
    {
        if (($listing['external_listing_id'] ?? '') === '') {
            throw ValidationException::withMessages(['listing' => 'Provider response did not identify the marketplace listing.']);
        }
        if (($listing['source_hash'] ?? '') === '') {
            $listing['source_hash'] = hash('sha256', json_encode($listing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
        $snapshot = MarketplaceListingSnapshot::query()->firstOrNew([
            'connection_id' => $proposal->connection_id,
            'external_listing_id' => $proposal->external_listing_id,
        ]);
        $snapshot->forceFill([
            'chatbot_id' => $proposal->chatbot_id,
            'provider' => $proposal->provider,
            'source_hash' => $listing['source_hash'],
            'snapshot' => $listing,
            'first_seen_at' => $snapshot->exists ? $snapshot->first_seen_at : now(),
            'last_seen_at' => now(),
        ])->save();

        return $snapshot->fresh();
    }
}
