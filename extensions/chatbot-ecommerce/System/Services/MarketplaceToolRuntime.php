<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceOrderSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceWriteProposal;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryConflict;
use App\Extensions\ChatbotEcommerce\System\Models\BrandVoiceProfile;
use App\Extensions\ChatbotEcommerce\System\Models\ListingIntelligenceRun;
use App\Extensions\ChatbotEcommerce\System\Models\ListingRewriteProposal;
use App\Extensions\ChatbotEcommerce\System\Models\ProductContentProfile;
use App\Extensions\ChatbotEcommerce\System\Support\CommerceRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class MarketplaceToolRuntime
{
    public function __construct(
        private readonly MarketplaceReadRuntime $reads,
        private readonly MarketplaceOrderImportRuntime $imports,
        private readonly MarketplaceCardRuntime $cards,
        private readonly MarketplaceWriteRuntime $writes,
        private readonly ListingIntelligenceRuntime $listingIntelligence,
        private readonly MarketplaceInventoryReconciliationRuntime $inventoryReconciliation,
    ) {}

    public function toolDefinitions(string $role): array
    {
        $common = [
            ['name'=>'marketplace_search_products','description'=>'[inform] Search configured marketplace connections.','parameters'=>['type'=>'object','properties'=>['session_id'=>['type'=>'string'],'query'=>['type'=>'string'],'filters'=>['type'=>'object'],'providers'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['session_id','query']]],
            ['name'=>'marketplace_get_listing','description'=>'[inform] Get a previously observed marketplace listing.','parameters'=>['type'=>'object','properties'=>['session_id'=>['type'=>'string'],'provider'=>['type'=>'string'],'external_listing_id'=>['type'=>'string']],'required'=>['session_id','provider','external_listing_id']]],
        ];
        if ($role === CommerceRole::SELLER_STEWARD) {
            $common[] = ['name'=>'seller_marketplace_import_orders','description'=>'[prepare] Import read-only external order snapshots after seller approval.','parameters'=>['type'=>'object','properties'=>['connection_uuid'=>['type'=>'string'],'seller_approved'=>['type'=>'boolean']],'required'=>['connection_uuid','seller_approved']]];
            $common[] = ['name'=>'seller_marketplace_orders','description'=>'[inform] List imported marketplace order snapshots.','parameters'=>['type'=>'object','properties'=>['provider'=>['type'=>'string'],'limit'=>['type'=>'integer']]]];
            $common[] = ['name'=>'seller_marketplace_prepare_write','description'=>'[prepare] Prepare one version-checked marketplace listing change. This never executes the change.','parameters'=>['type'=>'object','properties'=>['connection_uuid'=>['type'=>'string'],'external_listing_id'=>['type'=>'string'],'operation'=>['type'=>'string','enum'=>['update_listing','update_price','update_inventory','pause_listing','resume_listing']],'changes'=>['type'=>'object'],'expected_source_hash'=>['type'=>'string'],'idempotency_key'=>['type'=>'string']],'required'=>['connection_uuid','external_listing_id','operation','expected_source_hash','idempotency_key']]];
            $common[] = ['name'=>'seller_marketplace_approve_write','description'=>'[execute] Approve a prepared marketplace write with its one-time approval token. Can execute immediately only with explicit seller approval.','parameters'=>['type'=>'object','properties'=>['proposal_uuid'=>['type'=>'string'],'approval_token'=>['type'=>'string'],'seller_approved'=>['type'=>'boolean'],'execute_now'=>['type'=>'boolean']],'required'=>['proposal_uuid','approval_token','seller_approved']]];
            $common[] = ['name'=>'seller_marketplace_write_status','description'=>'[inform] Inspect a marketplace write proposal and its conflicts or execution state.','parameters'=>['type'=>'object','properties'=>['proposal_uuid'=>['type'=>'string']],'required'=>['proposal_uuid']]];
            $common[] = ['name'=>'seller_marketplace_rollback_write','description'=>'[execute] Roll back an executed marketplace write only if the listing has not changed afterward.','parameters'=>['type'=>'object','properties'=>['proposal_uuid'=>['type'=>'string'],'seller_approved'=>['type'=>'boolean']],'required'=>['proposal_uuid','seller_approved']]];
            $common[] = ['name'=>'seller_marketplace_analyse_listing','description'=>'[inform] Analyse an observed marketplace listing for policy, evidence and brand-voice risks.','parameters'=>['type'=>'object','properties'=>['connection_uuid'=>['type'=>'string'],'external_listing_id'=>['type'=>'string'],'product_content_profile_uuid'=>['type'=>'string'],'brand_voice_profile_uuid'=>['type'=>'string']],'required'=>['connection_uuid','external_listing_id']]];
            $common[] = ['name'=>'seller_marketplace_generate_rewrite','description'=>'[prepare] Generate a marketplace-specific rewrite from canonical product facts and the saved brand voice.','parameters'=>['type'=>'object','properties'=>['run_uuid'=>['type'=>'string']],'required'=>['run_uuid']]];
            $common[] = ['name'=>'seller_marketplace_prepare_rewrite_write','description'=>'[prepare] Convert a compliant rewrite into the normal version-checked marketplace write approval flow.','parameters'=>['type'=>'object','properties'=>['connection_uuid'=>['type'=>'string'],'rewrite_uuid'=>['type'=>'string'],'idempotency_key'=>['type'=>'string']],'required'=>['connection_uuid','rewrite_uuid','idempotency_key']]];
            $common[] = ['name'=>'seller_marketplace_inventory_scan','description'=>'[inform] Scan mapped marketplace stock against reservation-aware internal availability.','parameters'=>['type'=>'object','properties'=>['connection_uuid'=>['type'=>'string']],'required'=>['connection_uuid']]];
            $common[] = ['name'=>'seller_marketplace_inventory_conflicts','description'=>'[inform] List current marketplace inventory conflicts and oversell exposure.','parameters'=>['type'=>'object','properties'=>['status'=>['type'=>'string'],'severity'=>['type'=>'string'],'provider'=>['type'=>'string'],'limit'=>['type'=>'integer']]]];
            $common[] = ['name'=>'seller_marketplace_prepare_inventory_correction','description'=>'[prepare] Prepare a version-checked inventory correction. Seller approval is still required before execution.','parameters'=>['type'=>'object','properties'=>['conflict_uuid'=>['type'=>'string']],'required'=>['conflict_uuid']]];
            $common[] = ['name'=>'seller_marketplace_acknowledge_inventory_conflict','description'=>'[prepare] Acknowledge an inventory conflict without changing marketplace stock.','parameters'=>['type'=>'object','properties'=>['conflict_uuid'=>['type'=>'string']],'required'=>['conflict_uuid']]];
        }

        return $common;
    }

    public function execute(Chatbot $chatbot, string $function, array $arguments): array
    {
        if ($function === 'marketplace_search_products') {
            $search = $this->reads->createSearch($chatbot, (string) ($arguments['session_id'] ?? ''), (string) ($arguments['query'] ?? ''), (array) ($arguments['filters'] ?? []), (array) ($arguments['providers'] ?? []));
            return ['data' => $search->load('results'), 'ui' => $this->reads->card($search)];
        }
        if ($function === 'marketplace_get_listing') {
            $snapshot = $this->reads->listing($chatbot, (string) ($arguments['provider'] ?? ''), (string) ($arguments['external_listing_id'] ?? ''));
            if ($snapshot === null) {
                throw ValidationException::withMessages(['listing' => 'The marketplace listing has not been observed for this storefront.']);
            }
            return ['data' => $snapshot->snapshot, 'ui' => $this->cards->listing($snapshot)];
        }

        $this->assertSellerOwner($chatbot, $arguments);
        if ($function === 'seller_marketplace_import_orders') {
            $this->assertExplicitApproval($arguments);
            $connection = $this->connection($chatbot, (string) ($arguments['connection_uuid'] ?? ''));
            return ['data' => $this->imports->import($connection), 'ui' => null];
        }
        if ($function === 'seller_marketplace_orders') {
            $query = MarketplaceOrderSnapshot::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->with('lines')->latest('placed_at');
            if (! empty($arguments['provider'])) {
                $query->where('provider', strtolower((string) $arguments['provider']));
            }
            return ['data' => $query->limit(min(100, max(1, (int) ($arguments['limit'] ?? 25))))->get(), 'ui' => null];
        }
        if ($function === 'seller_marketplace_prepare_write') {
            $connection = $this->connection($chatbot, (string) ($arguments['connection_uuid'] ?? ''));
            $result = $this->writes->prepare(
                $chatbot,
                $connection,
                (string) ($arguments['external_listing_id'] ?? ''),
                (string) ($arguments['operation'] ?? ''),
                (array) ($arguments['changes'] ?? []),
                (string) ($arguments['expected_source_hash'] ?? ''),
                (string) ($arguments['idempotency_key'] ?? ''),
                (int) Auth::id()
            );
            $proposal = $result['proposal'];
            $blocked = (bool) (($proposal->conflict_state['blocking'] ?? false));
            return [
                'data' => $this->safeProposal($proposal),
                'ui' => [
                    'fallback_text' => $blocked
                        ? 'The marketplace change was not prepared because the listing or inventory state must be refreshed.'
                        : 'Marketplace change prepared. Review the before and after values, then approve it to continue.',
                    'schema' => [
                        'type' => 'marketplace_write_approval',
                        'proposal_uuid' => $proposal->uuid,
                        'operation' => $proposal->operation,
                        'before_state' => $proposal->before_state,
                        'requested_changes' => $proposal->requested_changes,
                        'conflict_state' => $proposal->conflict_state,
                        'approval_token' => $result['approval_token'],
                        'approval_expires_at' => $proposal->approval_expires_at?->toIso8601String(),
                    ],
                ],
            ];
        }
        if ($function === 'seller_marketplace_approve_write') {
            $this->assertExplicitApproval($arguments);
            $proposal = $this->proposal($chatbot, (string) ($arguments['proposal_uuid'] ?? ''));
            $approved = $this->writes->approve($proposal, (string) ($arguments['approval_token'] ?? ''), (int) Auth::id());
            if ((bool) ($arguments['execute_now'] ?? true)) {
                $approved = $this->writes->execute($approved);
            }
            return ['data' => $this->safeProposal($approved), 'ui' => ['fallback_text' => 'Marketplace write status: ' . $approved->status, 'schema' => ['type'=>'marketplace_write_status','proposal'=>$this->safeProposal($approved)]]];
        }
        if ($function === 'seller_marketplace_write_status') {
            $proposal = $this->proposal($chatbot, (string) ($arguments['proposal_uuid'] ?? ''));
            return ['data' => $this->safeProposal($proposal->load('attempts')), 'ui' => null];
        }
        if ($function === 'seller_marketplace_rollback_write') {
            $this->assertExplicitApproval($arguments);
            $proposal = $this->proposal($chatbot, (string) ($arguments['proposal_uuid'] ?? ''));
            $rolledBack = $this->writes->rollback($proposal, (int) Auth::id());
            return ['data' => $this->safeProposal($rolledBack), 'ui' => ['fallback_text' => 'The marketplace change was rolled back after confirming the listing had not changed.', 'schema' => ['type'=>'marketplace_write_status','proposal'=>$this->safeProposal($rolledBack)]]];
        }

        if ($function === 'seller_marketplace_analyse_listing') {
            $connection = $this->connection($chatbot, (string) ($arguments['connection_uuid'] ?? ''));
            $product = ! empty($arguments['product_content_profile_uuid'])
                ? ProductContentProfile::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('owner_user_id', (int) Auth::id())->where('uuid', (string) $arguments['product_content_profile_uuid'])->firstOrFail()
                : null;
            $brand = ! empty($arguments['brand_voice_profile_uuid'])
                ? BrandVoiceProfile::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('owner_user_id', (int) Auth::id())->where('uuid', (string) $arguments['brand_voice_profile_uuid'])->firstOrFail()
                : null;
            $run = $this->listingIntelligence->analyse($chatbot, $connection, (string) ($arguments['external_listing_id'] ?? ''), $product, $brand, (int) Auth::id());
            return ['data'=>$run,'ui'=>['fallback_text'=>'Listing analysis completed with '.(int)($run->compliance_summary['blocking_count'] ?? 0).' blocking findings and '.(int)($run->compliance_summary['warning_count'] ?? 0).' warnings.','schema'=>['type'=>'marketplace_listing_analysis','run_uuid'=>$run->uuid,'summary'=>$run->compliance_summary,'findings'=>$run->findings]]];
        }
        if ($function === 'seller_marketplace_generate_rewrite') {
            $run = ListingIntelligenceRun::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('owner_user_id', (int) Auth::id())->where('uuid', (string) ($arguments['run_uuid'] ?? ''))->firstOrFail();
            $rewrite = $this->listingIntelligence->generateRewrite($run, (int) Auth::id());
            return ['data'=>$rewrite,'ui'=>['fallback_text'=>(string)$rewrite->status === 'ready' ? 'A compliant marketplace rewrite is ready for review.' : 'The rewrite was generated but remains blocked by compliance or evidence findings.','schema'=>['type'=>'marketplace_listing_rewrite','rewrite_uuid'=>$rewrite->uuid,'status'=>$rewrite->status,'before'=>$rewrite->before_content,'proposed'=>$rewrite->proposed_content,'compliance'=>$rewrite->compliance_summary]]];
        }
        if ($function === 'seller_marketplace_prepare_rewrite_write') {
            $connection = $this->connection($chatbot, (string) ($arguments['connection_uuid'] ?? ''));
            $rewrite = ListingRewriteProposal::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('owner_user_id', (int) Auth::id())->where('uuid', (string) ($arguments['rewrite_uuid'] ?? ''))->firstOrFail();
            $result = $this->listingIntelligence->prepareWrite($chatbot, $connection, $rewrite, (string) ($arguments['idempotency_key'] ?? ''), (int) Auth::id());
            return ['data'=>['rewrite'=>$result['rewrite'],'proposal'=>$this->safeProposal($result['proposal'])],'ui'=>['fallback_text'=>'The listing rewrite has entered the standard marketplace write approval flow. Review it before approval.','schema'=>['type'=>'marketplace_write_approval','proposal_uuid'=>$result['proposal']->uuid,'approval_token'=>$result['approval_token'],'before_state'=>$result['proposal']->before_state,'requested_changes'=>$result['proposal']->requested_changes]]];
        }

        if ($function === 'seller_marketplace_inventory_scan') {
            $connection = $this->connection($chatbot, (string) ($arguments['connection_uuid'] ?? ''));
            $run = $this->inventoryReconciliation->scan($chatbot, $connection, (int) Auth::id());
            return ['data'=>$run,'ui'=>['fallback_text'=>'Inventory scan completed: '.(int)$run->critical_count.' critical conflict(s), '.(int)$run->conflict_count.' total conflict(s).','schema'=>['type'=>'marketplace_inventory_scan','scan_uuid'=>$run->uuid,'counts'=>['mappings'=>(int)$run->mapping_count,'in_sync'=>(int)$run->in_sync_count,'conflicts'=>(int)$run->conflict_count,'critical'=>(int)$run->critical_count,'corrections_prepared'=>(int)$run->corrections_prepared]]]];
        }
        if ($function === 'seller_marketplace_inventory_conflicts') {
            $query = MarketplaceInventoryConflict::query()->where('chatbot_id',(int)$chatbot->getAttribute('id'))->with(['mapping','writeProposal'])->latest('last_detected_at');
            foreach (['status','severity','provider'] as $filter) if (! empty($arguments[$filter])) $query->where($filter,(string)$arguments[$filter]);
            $items=$query->limit(min(100,max(1,(int)($arguments['limit'] ?? 25))))->get()->map(fn($conflict)=>$this->safeInventoryConflict($conflict));
            return ['data'=>$items,'ui'=>['fallback_text'=>'Found '.$items->count().' marketplace inventory conflict(s).','schema'=>['type'=>'marketplace_inventory_conflicts','items'=>$items]]];
        }
        if ($function === 'seller_marketplace_prepare_inventory_correction') {
            $conflict=$this->inventoryConflict($chatbot,(string)($arguments['conflict_uuid'] ?? ''));
            $result=$this->inventoryReconciliation->prepareCorrection($chatbot,$conflict,(int)Auth::id());
            return ['data'=>['conflict'=>$this->safeInventoryConflict($result['conflict']),'proposal'=>$result['proposal'] ? $this->safeProposal($result['proposal']) : null],'ui'=>['fallback_text'=>$result['proposal'] ? 'Inventory correction prepared. Review and approve the marketplace write to continue.' : 'The marketplace inventory is already in sync.','schema'=>['type'=>'marketplace_inventory_correction','conflict'=>$this->safeInventoryConflict($result['conflict']),'proposal_uuid'=>$result['proposal']?->uuid,'approval_token'=>$result['approval_token']]]];
        }
        if ($function === 'seller_marketplace_acknowledge_inventory_conflict') {
            $conflict=$this->inventoryConflict($chatbot,(string)($arguments['conflict_uuid'] ?? ''));
            $conflict=$this->inventoryReconciliation->acknowledge($conflict,(int)Auth::id());
            return ['data'=>$this->safeInventoryConflict($conflict),'ui'=>['fallback_text'=>'Inventory conflict acknowledged. No marketplace stock was changed.','schema'=>['type'=>'marketplace_inventory_conflict','conflict'=>$this->safeInventoryConflict($conflict)]]];
        }

        throw ValidationException::withMessages(['tool' => 'Unknown marketplace tool.']);
    }

    private function assertSellerOwner(Chatbot $chatbot, array $arguments): void
    {
        if (($arguments['commerce_role'] ?? CommerceRole::SELLER_STEWARD) !== CommerceRole::SELLER_STEWARD
            || Auth::id() === null
            || (int) Auth::id() !== (int) $chatbot->getAttribute('user_id')) {
            throw ValidationException::withMessages(['approval' => 'Authenticated seller access is required.']);
        }
    }

    private function assertExplicitApproval(array $arguments): void
    {
        if (! ($arguments['seller_approved'] ?? false)) {
            throw ValidationException::withMessages(['approval' => 'Explicit seller approval is required.']);
        }
    }

    private function connection(Chatbot $chatbot, string $uuid): MarketplaceConnection
    {
        return MarketplaceConnection::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('owner_user_id', (int) Auth::id())->where('uuid', $uuid)->firstOrFail();
    }

    private function proposal(Chatbot $chatbot, string $uuid): MarketplaceWriteProposal
    {
        return MarketplaceWriteProposal::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->where('owner_user_id', (int) Auth::id())->where('uuid', $uuid)->firstOrFail();
    }

    private function inventoryConflict(Chatbot $chatbot, string $uuid): MarketplaceInventoryConflict
    {
        return MarketplaceInventoryConflict::query()->where('chatbot_id',(int)$chatbot->getAttribute('id'))->where('uuid',$uuid)->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function safeInventoryConflict(MarketplaceInventoryConflict $conflict): array
    {
        return [
            'uuid'=>$conflict->uuid,'provider'=>$conflict->provider,'external_listing_id'=>$conflict->external_listing_id,
            'code'=>$conflict->code,'severity'=>$conflict->severity,'status'=>$conflict->status,
            'canonical_quantity'=>(int)$conflict->canonical_quantity,'target_quantity'=>(int)$conflict->target_quantity,
            'external_quantity'=>(int)$conflict->external_quantity,'delta'=>(int)$conflict->delta,
            'oversell_exposure'=>(int)$conflict->oversell_exposure,'mapping_verified'=>(bool)($conflict->mapping?->verified ?? false),
            'proposal_uuid'=>$conflict->writeProposal?->uuid,'last_detected_at'=>$conflict->last_detected_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function safeProposal(MarketplaceWriteProposal $proposal): array
    {
        return [
            'uuid' => $proposal->uuid,
            'provider' => $proposal->provider,
            'external_listing_id' => $proposal->external_listing_id,
            'operation' => $proposal->operation,
            'status' => $proposal->status,
            'before_state' => $proposal->before_state,
            'requested_changes' => $proposal->requested_changes,
            'after_state' => $proposal->after_state,
            'conflict_state' => $proposal->conflict_state,
            'executed_at' => $proposal->executed_at?->toIso8601String(),
            'rolled_back_at' => $proposal->rolled_back_at?->toIso8601String(),
        ];
    }
}
