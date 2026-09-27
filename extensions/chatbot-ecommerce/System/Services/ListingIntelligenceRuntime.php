<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\BrandVoiceProfile;
use App\Extensions\ChatbotEcommerce\System\Models\ListingComplianceRule;
use App\Extensions\ChatbotEcommerce\System\Models\ListingIntelligenceFinding;
use App\Extensions\ChatbotEcommerce\System\Models\ListingIntelligenceRun;
use App\Extensions\ChatbotEcommerce\System\Models\ListingRewriteProposal;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceListingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\ProductContentProfile;
use App\Extensions\ChatbotEcommerce\System\Support\BrandVoiceGuide;
use App\Extensions\ChatbotEcommerce\System\Support\ClaimEvidenceValidator;
use App\Extensions\ChatbotEcommerce\System\Support\ListingComplianceScanner;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceListingComposer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ListingIntelligenceRuntime
{
    public function __construct(private readonly MarketplaceWriteRuntime $writes) {}

    /** @param array<string,mixed> $data */
    public function saveBrandVoice(Chatbot $chatbot, array $data, int $userId, ?BrandVoiceProfile $profile = null): BrandVoiceProfile
    {
        $this->assertEnabled();
        $this->assertOwner($chatbot, $userId);
        if ($profile !== null && ((int) $profile->chatbot_id !== (int) $chatbot->getAttribute('id') || (int) $profile->owner_user_id !== $userId)) abort(404);
        $guide = BrandVoiceGuide::fromExamples((array) ($data['examples'] ?? []), (array) ($data['tone'] ?? []), (array) ($data['forbidden_terms'] ?? []), (array) ($data['preferred_terms'] ?? []));
        $target = $profile ?? new BrandVoiceProfile();
        $target->forceFill([
            'chatbot_id'=>(int)$chatbot->getAttribute('id'),'owner_user_id'=>$userId,'name'=>trim((string)($data['name'] ?? 'Default')),
            'examples'=>$guide['examples'],'tone'=>$guide['tone'],'preferred_terms'=>$guide['preferred_terms'],'forbidden_terms'=>$guide['forbidden_terms'],
            'guide'=>$guide,'fingerprint'=>$guide['fingerprint'],'active'=>(bool)($data['active'] ?? true),
        ])->save();
        return $target->fresh();
    }

    /** @param array<string,mixed> $data */
    public function saveProductProfile(Chatbot $chatbot, array $data, int $userId, ?ProductContentProfile $profile = null): ProductContentProfile
    {
        $this->assertEnabled();
        $this->assertOwner($chatbot, $userId);
        if ($profile !== null && ((int) $profile->chatbot_id !== (int) $chatbot->getAttribute('id') || (int) $profile->owner_user_id !== $userId)) abort(404);
        $payload = [
            'canonical_name'=>trim((string)($data['canonical_name'] ?? '')),'brand'=>trim((string)($data['brand'] ?? '')),'summary'=>trim((string)($data['summary'] ?? '')),
            'features'=>array_values((array)($data['features'] ?? [])),'attributes'=>(array)($data['attributes'] ?? []),'materials'=>array_values((array)($data['materials'] ?? [])),
            'dimensions'=>(array)($data['dimensions'] ?? []),'compatibility'=>array_values((array)($data['compatibility'] ?? [])),'intended_users'=>array_values((array)($data['intended_users'] ?? [])),
            'contraindications'=>array_values((array)($data['contraindications'] ?? [])),'claims'=>array_values((array)($data['claims'] ?? [])),'evidence_refs'=>array_values((array)($data['evidence_refs'] ?? [])),
            'semantic_tags'=>array_values((array)($data['semantic_tags'] ?? [])),
        ];
        if ($payload['canonical_name'] === '') throw ValidationException::withMessages(['canonical_name'=>'A canonical product name is required.']);
        $target = $profile ?? new ProductContentProfile();
        $target->forceFill($payload + [
            'chatbot_id'=>(int)$chatbot->getAttribute('id'),'owner_user_id'=>$userId,'product_id'=>$data['product_id'] ?? null,'sku'=>$data['sku'] ?? null,
            'content_hash'=>hash('sha256',json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),'metadata'=>(array)($data['metadata'] ?? []),
        ])->save();
        return $target->fresh();
    }

    /** @param array<string,mixed> $data */
    public function saveRule(Chatbot $chatbot, array $data, int $userId, ?ListingComplianceRule $rule = null): ListingComplianceRule
    {
        $this->assertEnabled();
        $this->assertOwner($chatbot, $userId);
        if ($rule !== null && ((int) $rule->chatbot_id !== (int) $chatbot->getAttribute('id') || (int) $rule->owner_user_id !== $userId)) abort(404);
        $target = $rule ?? new ListingComplianceRule();
        $target->forceFill([
            'chatbot_id'=>(int)$chatbot->getAttribute('id'),'owner_user_id'=>$userId,'provider'=>strtolower((string)($data['provider'] ?? 'generic')),
            'code'=>trim((string)($data['code'] ?? 'custom_rule')),'severity'=>(string)($data['severity'] ?? 'warning'),'match_type'=>'contains',
            'terms'=>array_values((array)($data['terms'] ?? [])),'field_scopes'=>array_values((array)($data['field_scopes'] ?? ['title','description','bullet_points','tags'])),
            'message'=>(string)($data['message'] ?? 'Review this listing content.'),'remediation'=>(string)($data['remediation'] ?? 'Revise or substantiate the flagged wording.'),
            'priority'=>(int)($data['priority'] ?? 100),'active'=>(bool)($data['active'] ?? true),'metadata'=>(array)($data['metadata'] ?? []),
        ])->save();
        return $target->fresh();
    }

    public function analyse(Chatbot $chatbot, MarketplaceConnection $connection, string $externalListingId, ?ProductContentProfile $product, ?BrandVoiceProfile $brandVoice, int $userId): ListingIntelligenceRun
    {
        $this->assertEnabled();
        $this->assertConnection($chatbot,$connection,$userId);
        if ($product !== null && ((int) $product->chatbot_id !== (int) $chatbot->getAttribute('id') || (int) $product->owner_user_id !== $userId)) abort(404);
        if ($brandVoice !== null && ((int) $brandVoice->chatbot_id !== (int) $chatbot->getAttribute('id') || (int) $brandVoice->owner_user_id !== $userId)) abort(404);
        $listing = MarketplaceListingSnapshot::query()->where('connection_id',$connection->id)->where('external_listing_id',$externalListingId)->latest('last_seen_at')->firstOrFail();
        $source = (array)$listing->snapshot;
        $rules = $this->rules($chatbot,(string)$connection->provider);
        $guide = (array)($brandVoice?->guide ?? []);
        $scan = ListingComplianceScanner::scan($source,$rules,$guide);
        $evidence = ClaimEvidenceValidator::validate((array)($product?->claims ?? []));
        if ($evidence['unsupported_count'] > 0) {
            $scan['findings'][] = ['code'=>'unsupported_product_claim','severity'=>'block','field'=>'claims','matched_value'=>(string)$evidence['unsupported_count'],'message'=>'One or more product claims have no evidence reference.','remediation'=>'Attach evidence or remove the unsupported claim.'];
            $scan['blocking_count']++;
            $scan['publishable']=false;
        }
        return DB::transaction(function () use ($chatbot,$connection,$listing,$product,$brandVoice,$userId,$source,$scan,$evidence): ListingIntelligenceRun {
            $run=ListingIntelligenceRun::query()->create([
                'chatbot_id'=>(int)$chatbot->getAttribute('id'),'owner_user_id'=>$userId,'connection_id'=>$connection->id,'listing_snapshot_id'=>$listing->id,
                'product_content_profile_id'=>$product?->id,'brand_voice_profile_id'=>$brandVoice?->id,'provider'=>$connection->provider,'external_listing_id'=>$listing->external_listing_id,
                'status'=>'analysed','source_hash'=>(string)$listing->source_hash,'source_content'=>$source,'evidence_report'=>$evidence,'compliance_summary'=>array_diff_key($scan,['findings'=>true]),
                'content_hash'=>hash('sha256',json_encode($source,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),'completed_at'=>now(),
            ]);
            foreach ($scan['findings'] as $finding) ListingIntelligenceFinding::query()->create([
                'run_id'=>$run->id,'code'=>$finding['code'],'severity'=>$finding['severity'],'field'=>$finding['field'] ?? null,'matched_value'=>$finding['matched_value'] ?? null,
                'message'=>$finding['message'],'remediation'=>$finding['remediation'] ?? null,
            ]);
            return $run->fresh(['findings','rewrites']);
        });
    }

    public function generateRewrite(ListingIntelligenceRun $run, int $userId): ListingRewriteProposal
    {
        $this->assertEnabled();
        $this->assertRunOwner($run,$userId);
        $product = $run->product_content_profile_id ? ProductContentProfile::query()->find($run->product_content_profile_id) : null;
        if ($product === null) throw ValidationException::withMessages(['product_content_profile'=>'A canonical product content profile is required to generate a rewrite.']);
        $brand = $run->brand_voice_profile_id ? BrandVoiceProfile::query()->find($run->brand_voice_profile_id) : null;
        $guide=(array)($brand?->guide ?? []);
        $canonical=[
            'name'=>$product->canonical_name,'brand'=>$product->brand,'summary'=>$product->summary,'features'=>$product->features,
            'attributes'=>$product->attributes,'claims'=>$product->claims,'semantic_tags'=>$product->semantic_tags,
        ];
        $content=MarketplaceListingComposer::compose((string)$run->provider,$canonical,$guide);
        $scan=ListingComplianceScanner::scan($content,$this->rulesByIds((int)$run->chatbot_id,(string)$run->provider),$guide);
        $evidence=ClaimEvidenceValidator::validate((array)$product->claims);
        if ($evidence['unsupported_count'] > 0) {
            $scan['blocking_count']+=(int)$evidence['unsupported_count']; $scan['publishable']=false;
            $scan['findings'][]=['code'=>'unsupported_product_claim','severity'=>'block','field'=>'claims','matched_value'=>(string)$evidence['unsupported_count'],'message'=>'Generated copy relies on unsupported product claims.','remediation'=>'Add evidence references or remove those claims.'];
        }
        $proposalHash=hash('sha256',json_encode([$run->source_hash,$content,$scan],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $proposal=ListingRewriteProposal::query()->firstOrNew(['run_id'=>$run->id,'proposal_hash'=>$proposalHash]);
        $proposal->forceFill([
            'chatbot_id'=>$run->chatbot_id,'owner_user_id'=>$userId,'status'=>$scan['publishable']?'ready':'blocked','source_hash'=>$run->source_hash,
            'before_content'=>$run->source_content,'proposed_content'=>$content,'compliance_summary'=>array_diff_key($scan,['findings'=>true]),'metadata'=>['findings'=>$scan['findings'],'evidence_report'=>$evidence],
        ])->save();
        $run->forceFill(['status'=>'rewrite_generated','generated_content'=>$content,'compliance_summary'=>array_diff_key($scan,['findings'=>true])])->save();
        return $proposal->fresh();
    }

    /** @return array{rewrite:ListingRewriteProposal,proposal:mixed,approval_token:?string} */
    public function prepareWrite(Chatbot $chatbot, MarketplaceConnection $connection, ListingRewriteProposal $rewrite, string $idempotencyKey, int $userId): array
    {
        $this->assertEnabled();
        $this->assertConnection($chatbot,$connection,$userId);
        if ((int)$rewrite->chatbot_id !== (int)$chatbot->getAttribute('id') || (int)$rewrite->owner_user_id !== $userId) abort(404);
        if ((string)$rewrite->status !== 'ready' || (int)($rewrite->compliance_summary['blocking_count'] ?? 0) > 0) throw ValidationException::withMessages(['rewrite'=>'A blocked listing rewrite cannot be prepared for publication.']);
        $run=ListingIntelligenceRun::query()->findOrFail($rewrite->run_id);
        if ((int) $run->connection_id !== (int) $connection->id) throw ValidationException::withMessages(['connection'=>'The rewrite belongs to another marketplace connection.']);
        if (! hash_equals((string)$run->source_hash,(string)$rewrite->source_hash)) throw ValidationException::withMessages(['source_hash'=>'The rewrite no longer matches its source listing.']);
        $result=$this->writes->prepare($chatbot,$connection,(string)$run->external_listing_id,'update_listing',(array)$rewrite->proposed_content,(string)$rewrite->source_hash,$idempotencyKey,$userId);
        $rewrite->forceFill(['status'=>'write_prepared','marketplace_write_proposal_id'=>$result['proposal']->id,'prepared_at'=>now()])->save();
        return ['rewrite'=>$rewrite->fresh(),'proposal'=>$result['proposal'],'approval_token'=>$result['approval_token']];
    }

    /** @return array<int,array<string,mixed>> */
    private function rules(Chatbot $chatbot,string $provider): array { return $this->rulesByIds((int)$chatbot->getAttribute('id'),$provider); }
    /** @return array<int,array<string,mixed>> */
    private function rulesByIds(int $chatbotId,string $provider): array
    {
        $rows=ListingComplianceRule::query()->where('active',true)->whereIn('provider',['generic',strtolower($provider)])->where(function($q) use($chatbotId){$q->whereNull('chatbot_id')->orWhere('chatbot_id',$chatbotId);})->orderBy('priority')->get();
        $rules=$rows->map(fn(ListingComplianceRule $r)=>['code'=>$r->code,'severity'=>$r->severity,'terms'=>$r->terms,'field_scopes'=>$r->field_scopes,'message'=>$r->message,'remediation'=>$r->remediation])->all();
        return array_merge($this->defaultRules(), $rules);
    }
    /** @return array<int,array<string,mixed>> */
    private function defaultRules(): array
    {
        return [
            ['code'=>'unsupported_health_claim','severity'=>'block','terms'=>['cure','treat disease','prevent disease'],'field_scopes'=>['title','description','bullet_points'],'message'=>'Health or disease claims require explicit evidence and marketplace permission.','remediation'=>'Remove the claim or attach approved evidence.'],
            ['code'=>'absolute_guarantee','severity'=>'warning','terms'=>['guaranteed','100% effective','best ever'],'field_scopes'=>['title','description','bullet_points'],'message'=>'Absolute promotional claims may be misleading.','remediation'=>'Use specific, supportable product facts.'],
        ];
    }
    private function assertEnabled(): void { if (! (bool) config('chatbot-ecommerce.listing_intelligence.enabled', true)) throw ValidationException::withMessages(['listing_intelligence'=>'Listing intelligence is disabled.']); }
    private function assertConnection(Chatbot $chatbot,MarketplaceConnection $connection,int $userId): void
    {
        $this->assertOwner($chatbot,$userId);
        if ((int)$connection->chatbot_id !== (int)$chatbot->getAttribute('id') || (int)$connection->owner_user_id !== $userId) abort(404);
    }
    private function assertRunOwner(ListingIntelligenceRun $run,int $userId): void { if ((int)$run->owner_user_id !== $userId) abort(404); }
    private function assertOwner(Chatbot $chatbot,int $userId): void { if ((int)$chatbot->getAttribute('user_id') !== $userId) abort(403); }
}
