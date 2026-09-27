<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\MarketplaceInventoryConflictResource;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\MarketplaceInventoryScanRunResource;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryConflict;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryMapping;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryPolicy;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryScanRun;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceListingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryLocation;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceInventoryReconciliationRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

final class MarketplaceInventoryAdminApiController extends Controller
{
    public function policies(Chatbot $chatbot)
    {
        $this->assertOwner($chatbot);
        return MarketplaceInventoryPolicy::query()->where('chatbot_id',$chatbot->getAttribute('id'))->where('owner_user_id',Auth::id())->latest('id')->get();
    }

    public function storePolicy(Chatbot $chatbot, Request $request): JsonResponse
    {
        $this->assertOwner($chatbot);
        $data = $this->policyData($request);
        if (! empty($data['connection_id'])) $this->ownedConnection($chatbot, (int)$data['connection_id']);
        $policy = MarketplaceInventoryPolicy::query()->updateOrCreate(
            ['chatbot_id'=>(int)$chatbot->getAttribute('id'),'connection_id'=>$data['connection_id'] ?? null],
            $data + ['owner_user_id'=>(int)Auth::id()]
        );
        return response()->json(['data'=>$policy], 201);
    }

    public function updatePolicy(Chatbot $chatbot, MarketplaceInventoryPolicy $policy, Request $request)
    {
        $this->assertOwnedPolicy($chatbot, $policy);
        $policy->forceFill($this->policyData($request, true))->save();
        return $policy->fresh();
    }

    public function mappings(Chatbot $chatbot, Request $request)
    {
        $this->assertOwner($chatbot);
        $query = MarketplaceInventoryMapping::query()->where('chatbot_id',$chatbot->getAttribute('id'))->with(['connection','variant','location'])->latest('id');
        if ($request->filled('connection_uuid')) {
            $connection = $this->ownedConnectionByUuid($chatbot, (string)$request->string('connection_uuid'));
            $query->where('connection_id',$connection->id);
        }
        if ($request->filled('active')) $query->where('active',$request->boolean('active'));
        return $query->paginate(min(100,max(1,$request->integer('per_page',25))));
    }

    public function storeMapping(Chatbot $chatbot, Request $request): JsonResponse
    {
        $this->assertOwner($chatbot);
        $data = $request->validate([
            'connection_uuid'=>['required','string','max:191'],'external_listing_id'=>['required','string','max:191'],
            'variant_id'=>['required','integer','min:1'],'location_id'=>['nullable','integer','min:1'],
            'allocation_mode'=>['nullable','in:equal_share,mirror,percentage,fixed_cap,percentage_cap'],'allocation_bps'=>['nullable','integer','between:0,10000'],
            'buffer_quantity'=>['nullable','integer','min:0'],'minimum_quantity'=>['nullable','integer','min:0'],'maximum_quantity'=>['nullable','integer','min:0'],
            'verified'=>['sometimes','boolean'],'active'=>['sometimes','boolean'],
        ]);
        $connection = $this->ownedConnectionByUuid($chatbot,(string)$data['connection_uuid']);
        $this->ownedVariant($chatbot, (int) $data['variant_id']);
        if (! empty($data['location_id'])) { $this->ownedLocation($chatbot, (int) $data['location_id']); }
        $listing = MarketplaceListingSnapshot::query()->where('connection_id',$connection->id)->where('external_listing_id',(string)$data['external_listing_id'])->firstOrFail();
        $mapping = MarketplaceInventoryMapping::query()->updateOrCreate(
            ['connection_id'=>$connection->id,'external_listing_id'=>(string)$data['external_listing_id']],
            [
                'chatbot_id'=>(int)$chatbot->getAttribute('id'),'listing_snapshot_id'=>$listing->id,'variant_id'=>(int)$data['variant_id'],
                'location_id'=>$data['location_id'] ?? null,'sku'=>$this->sku($listing),'allocation_mode'=>$data['allocation_mode'] ?? null,
                'allocation_bps'=>$data['allocation_bps'] ?? null,'buffer_quantity'=>$data['buffer_quantity'] ?? null,
                'minimum_quantity'=>$data['minimum_quantity'] ?? null,'maximum_quantity'=>$data['maximum_quantity'] ?? null,
                'verified'=>(bool)($data['verified'] ?? false),'active'=>(bool)($data['active'] ?? true),'metadata'=>['mapping_source'=>'seller'],
            ]
        );
        return response()->json(['data'=>$mapping->load(['connection','variant','location'])],201);
    }

    public function updateMapping(Chatbot $chatbot, MarketplaceInventoryMapping $mapping, Request $request)
    {
        $this->assertOwnedMapping($chatbot,$mapping);
        $data = $request->validate([
            'variant_id'=>['sometimes','integer','min:1'],'location_id'=>['nullable','integer','min:1'],
            'allocation_mode'=>['nullable','in:equal_share,mirror,percentage,fixed_cap,percentage_cap'],'allocation_bps'=>['nullable','integer','between:0,10000'],
            'buffer_quantity'=>['nullable','integer','min:0'],'minimum_quantity'=>['nullable','integer','min:0'],'maximum_quantity'=>['nullable','integer','min:0'],
            'verified'=>['sometimes','boolean'],'active'=>['sometimes','boolean'],
        ]);
        if (isset($data['variant_id'])) { $this->ownedVariant($chatbot, (int) $data['variant_id']); }
        if (array_key_exists('location_id', $data) && $data['location_id'] !== null) { $this->ownedLocation($chatbot, (int) $data['location_id']); }
        $mapping->forceFill($data)->save();
        return $mapping->fresh(['connection','variant','location']);
    }

    public function scans(Chatbot $chatbot, Request $request)
    {
        $this->assertOwner($chatbot);
        return MarketplaceInventoryScanRunResource::collection(MarketplaceInventoryScanRun::query()->where('chatbot_id',$chatbot->getAttribute('id'))->latest('id')->paginate(min(100,max(1,$request->integer('per_page',25)))));
    }

    public function scan(Chatbot $chatbot, MarketplaceConnection $connection, MarketplaceInventoryReconciliationRuntime $runtime): MarketplaceInventoryScanRunResource
    {
        $this->assertOwner($chatbot); $this->assertOwnedConnection($chatbot,$connection);
        return new MarketplaceInventoryScanRunResource($runtime->scan($chatbot,$connection,(int)Auth::id())->load('conflicts.mapping','conflicts.writeProposal','conflicts.events'));
    }

    public function conflicts(Chatbot $chatbot, Request $request)
    {
        $this->assertOwner($chatbot);
        $query = MarketplaceInventoryConflict::query()->where('chatbot_id',$chatbot->getAttribute('id'))->with(['mapping','writeProposal','events'])->latest('last_detected_at');
        foreach (['status','severity','provider','code'] as $filter) if ($request->filled($filter)) $query->where($filter,$request->string($filter));
        return MarketplaceInventoryConflictResource::collection($query->paginate(min(100,max(1,$request->integer('per_page',25)))));
    }

    public function showConflict(Chatbot $chatbot, MarketplaceInventoryConflict $conflict): MarketplaceInventoryConflictResource
    {
        $this->assertOwnedConflict($chatbot,$conflict);
        return new MarketplaceInventoryConflictResource($conflict->load(['mapping','writeProposal','events']));
    }

    public function acknowledge(Chatbot $chatbot, MarketplaceInventoryConflict $conflict, MarketplaceInventoryReconciliationRuntime $runtime): MarketplaceInventoryConflictResource
    {
        $this->assertOwnedConflict($chatbot,$conflict);
        return new MarketplaceInventoryConflictResource($runtime->acknowledge($conflict,(int)Auth::id()));
    }

    public function ignore(Chatbot $chatbot, MarketplaceInventoryConflict $conflict, Request $request, MarketplaceInventoryReconciliationRuntime $runtime): MarketplaceInventoryConflictResource
    {
        $this->assertOwnedConflict($chatbot,$conflict);
        $data=$request->validate(['reason'=>['required','string','max:2000'],'hours'=>['nullable','integer','between:1,720']]);
        return new MarketplaceInventoryConflictResource($runtime->ignore($conflict,(int)Auth::id(),(string)$data['reason'],$data['hours'] ?? null));
    }

    public function prepareCorrection(Chatbot $chatbot, MarketplaceInventoryConflict $conflict, MarketplaceInventoryReconciliationRuntime $runtime): JsonResponse
    {
        $this->assertOwnedConflict($chatbot,$conflict);
        $result=$runtime->prepareCorrection($chatbot,$conflict,(int)Auth::id());
        return response()->json(['data'=>[
            'conflict'=>(new MarketplaceInventoryConflictResource($result['conflict']->load(['mapping','writeProposal','events'])))->resolve(request()),
            'proposal'=>$result['proposal'],'approval_token'=>$result['approval_token'],
        ]]);
    }

    public function refreshConflict(Chatbot $chatbot, MarketplaceInventoryConflict $conflict, MarketplaceInventoryReconciliationRuntime $runtime): MarketplaceInventoryConflictResource
    {
        $this->assertOwnedConflict($chatbot,$conflict); $conflict->loadMissing('mapping.connection');
        $runtime->scanMapping($chatbot,$conflict->mapping->connection,$conflict->mapping,null,(int)Auth::id());
        return new MarketplaceInventoryConflictResource($conflict->fresh()->load(['mapping','writeProposal','events']));
    }

    private function policyData(Request $request, bool $partial=false): array
    {
        $required=$partial?'sometimes':'required';
        return $request->validate([
            'connection_id'=>['nullable','integer','min:1'],'source_of_truth'=>[$required,'in:internal,marketplace'],
            'resolution_mode'=>[$required,'in:observe_only,suggest_only,auto_prepare'],'allocation_mode'=>[$required,'in:equal_share,mirror,percentage,fixed_cap,percentage_cap'],
            'allocation_bps'=>['sometimes','integer','between:0,10000'],'buffer_quantity'=>['sometimes','integer','min:0'],
            'minimum_quantity'=>['sometimes','integer','min:0'],'maximum_quantity'=>['nullable','integer','min:0'],
            'maximum_sync_age_seconds'=>['sometimes','integer','between:30,86400'],'quantity_tolerance'=>['sometimes','integer','between:0,1000'],
            'maximum_auto_delta'=>['sometimes','integer','between:0,1000'],'require_fresh_snapshot'=>['sometimes','boolean'],
            'auto_map_by_sku'=>['sometimes','boolean'],'active'=>['sometimes','boolean'],
        ]);
    }

    private function assertOwner(Chatbot $chatbot): void { abort_unless(Auth::id()!==null && (int)$chatbot->getAttribute('user_id')===(int)Auth::id(),403); }
    private function assertOwnedConnection(Chatbot $chatbot, MarketplaceConnection $connection): void { abort_unless((int)$connection->chatbot_id===(int)$chatbot->getAttribute('id') && (int)$connection->owner_user_id===(int)Auth::id(),404); }
    private function ownedVariant(Chatbot $chatbot, int $variantId): ProductVariant
    {
        return ProductVariant::query()->whereKey($variantId)->whereHas('product', fn ($query) => $query->where('chatbot_id', $chatbot->getAttribute('id')))->firstOrFail();
    }

    private function ownedLocation(Chatbot $chatbot, int $locationId): InventoryLocation
    {
        return InventoryLocation::query()->whereKey($locationId)->where('chatbot_id', $chatbot->getAttribute('id'))->firstOrFail();
    }

    private function ownedConnection(Chatbot $chatbot,int $id): MarketplaceConnection { return MarketplaceConnection::query()->where('id',$id)->where('chatbot_id',$chatbot->getAttribute('id'))->where('owner_user_id',Auth::id())->firstOrFail(); }
    private function ownedConnectionByUuid(Chatbot $chatbot,string $uuid): MarketplaceConnection { return MarketplaceConnection::query()->where('uuid',$uuid)->where('chatbot_id',$chatbot->getAttribute('id'))->where('owner_user_id',Auth::id())->firstOrFail(); }
    private function assertOwnedMapping(Chatbot $chatbot,MarketplaceInventoryMapping $mapping): void { $this->assertOwner($chatbot); abort_unless((int)$mapping->chatbot_id===(int)$chatbot->getAttribute('id'),404); }
    private function assertOwnedPolicy(Chatbot $chatbot,MarketplaceInventoryPolicy $policy): void { $this->assertOwner($chatbot); abort_unless((int)$policy->chatbot_id===(int)$chatbot->getAttribute('id') && (int)$policy->owner_user_id===(int)Auth::id(),404); }
    private function assertOwnedConflict(Chatbot $chatbot,MarketplaceInventoryConflict $conflict): void { $this->assertOwner($chatbot); abort_unless((int)$conflict->chatbot_id===(int)$chatbot->getAttribute('id'),404); }
    private function sku(MarketplaceListingSnapshot $listing): string { $s=(array)$listing->snapshot; $a=is_array($s['attributes']??null)?$s['attributes']:[]; return trim((string)($s['sku']??$a['sku']??$a['seller_sku']??'')); }
}
