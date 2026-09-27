<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\ListingIntelligenceRunResource;
use App\Extensions\ChatbotEcommerce\System\Models\BrandVoiceProfile;
use App\Extensions\ChatbotEcommerce\System\Models\ListingComplianceRule;
use App\Extensions\ChatbotEcommerce\System\Models\ListingIntelligenceRun;
use App\Extensions\ChatbotEcommerce\System\Models\ListingRewriteProposal;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\ProductContentProfile;
use App\Extensions\ChatbotEcommerce\System\Services\ListingIntelligenceRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceWriteRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ListingIntelligenceAdminApiController extends Controller
{
    public function brandVoices(Chatbot $chatbot) { $this->assertOwner($chatbot); return BrandVoiceProfile::query()->where('chatbot_id',$chatbot->id)->where('owner_user_id',Auth::id())->latest('id')->paginate(50); }
    public function storeBrandVoice(Chatbot $chatbot,Request $request,ListingIntelligenceRuntime $runtime): JsonResponse
    {
        $data=$request->validate(['name'=>['required','string','max:191'],'examples'=>['required','array','max:20'],'examples.*'=>['string','max:5000'],'tone'=>['sometimes','array','max:12'],'preferred_terms'=>['sometimes','array','max:100'],'forbidden_terms'=>['sometimes','array','max:100'],'active'=>['sometimes','boolean']]);
        return response()->json(['data'=>$runtime->saveBrandVoice($chatbot,$data,(int)Auth::id())],201);
    }
    public function updateBrandVoice(Chatbot $chatbot,BrandVoiceProfile $brandVoice,Request $request,ListingIntelligenceRuntime $runtime): JsonResponse
    {
        $this->assertOwned($chatbot,$brandVoice); $data=$request->validate(['name'=>['sometimes','string','max:191'],'examples'=>['sometimes','array','max:20'],'tone'=>['sometimes','array','max:12'],'preferred_terms'=>['sometimes','array','max:100'],'forbidden_terms'=>['sometimes','array','max:100'],'active'=>['sometimes','boolean']]);
        $merged=array_merge($brandVoice->toArray(),$data); return response()->json(['data'=>$runtime->saveBrandVoice($chatbot,$merged,(int)Auth::id(),$brandVoice)]);
    }
    public function productProfiles(Chatbot $chatbot) { $this->assertOwner($chatbot); return ProductContentProfile::query()->where('chatbot_id',$chatbot->id)->where('owner_user_id',Auth::id())->latest('id')->paginate(50); }
    public function storeProductProfile(Chatbot $chatbot,Request $request,ListingIntelligenceRuntime $runtime): JsonResponse
    {
        $data=$request->validate(['canonical_name'=>['required','string','max:255'],'brand'=>['nullable','string','max:191'],'summary'=>['nullable','string','max:20000'],'product_id'=>['nullable','integer'],'sku'=>['nullable','string','max:191'],'features'=>['sometimes','array','max:50'],'attributes'=>['sometimes','array'],'materials'=>['sometimes','array'],'dimensions'=>['sometimes','array'],'compatibility'=>['sometimes','array'],'intended_users'=>['sometimes','array'],'contraindications'=>['sometimes','array'],'claims'=>['sometimes','array','max:200'],'evidence_refs'=>['sometimes','array'],'semantic_tags'=>['sometimes','array','max:100'],'metadata'=>['sometimes','array']]);
        return response()->json(['data'=>$runtime->saveProductProfile($chatbot,$data,(int)Auth::id())],201);
    }
    public function updateProductProfile(Chatbot $chatbot,ProductContentProfile $productProfile,Request $request,ListingIntelligenceRuntime $runtime): JsonResponse
    {
        $this->assertOwned($chatbot,$productProfile); $data=$request->all(); return response()->json(['data'=>$runtime->saveProductProfile($chatbot,array_merge($productProfile->toArray(),$data),(int)Auth::id(),$productProfile)]);
    }
    public function rules(Chatbot $chatbot) { $this->assertOwner($chatbot); return ListingComplianceRule::query()->where('chatbot_id',$chatbot->id)->where('owner_user_id',Auth::id())->orderBy('priority')->paginate(100); }
    public function storeRule(Chatbot $chatbot,Request $request,ListingIntelligenceRuntime $runtime): JsonResponse
    {
        $data=$request->validate(['provider'=>['required','in:amazon,ebay,etsy,generic'],'code'=>['required','string','max:100'],'severity'=>['required','in:info,warning,block'],'terms'=>['required','array','max:100'],'field_scopes'=>['sometimes','array','max:20'],'message'=>['required','string','max:5000'],'remediation'=>['nullable','string','max:5000'],'priority'=>['sometimes','integer','min:1','max:10000'],'active'=>['sometimes','boolean']]);
        return response()->json(['data'=>$runtime->saveRule($chatbot,$data,(int)Auth::id())],201);
    }
    public function runs(Chatbot $chatbot) { $this->assertOwner($chatbot); return ListingIntelligenceRunResource::collection(ListingIntelligenceRun::query()->where('chatbot_id',$chatbot->id)->where('owner_user_id',Auth::id())->with(['findings','rewrites'])->latest('id')->paginate(25)); }
    public function show(Chatbot $chatbot,ListingIntelligenceRun $run): ListingIntelligenceRunResource { $this->assertOwned($chatbot,$run); return new ListingIntelligenceRunResource($run->load(['findings','rewrites'])); }
    public function analyse(Chatbot $chatbot,MarketplaceConnection $connection,string $externalListingId,Request $request,ListingIntelligenceRuntime $runtime): ListingIntelligenceRunResource
    {
        $this->assertOwned($chatbot,$connection); $data=$request->validate(['product_content_profile_uuid'=>['nullable','uuid'],'brand_voice_profile_uuid'=>['nullable','uuid']]);
        $product=!empty($data['product_content_profile_uuid'])?ProductContentProfile::query()->where('chatbot_id',$chatbot->id)->where('owner_user_id',Auth::id())->where('uuid',$data['product_content_profile_uuid'])->firstOrFail():null;
        $brand=!empty($data['brand_voice_profile_uuid'])?BrandVoiceProfile::query()->where('chatbot_id',$chatbot->id)->where('owner_user_id',Auth::id())->where('uuid',$data['brand_voice_profile_uuid'])->firstOrFail():null;
        return new ListingIntelligenceRunResource($runtime->analyse($chatbot,$connection,$externalListingId,$product,$brand,(int)Auth::id()));
    }
    public function generateRewrite(Chatbot $chatbot,ListingIntelligenceRun $run,ListingIntelligenceRuntime $runtime): JsonResponse
    {
        $this->assertOwned($chatbot,$run); return response()->json(['data'=>$runtime->generateRewrite($run,(int)Auth::id())],201);
    }
    public function prepareWrite(Chatbot $chatbot,MarketplaceConnection $connection,ListingRewriteProposal $rewrite,Request $request,ListingIntelligenceRuntime $runtime): JsonResponse
    {
        $this->assertOwned($chatbot,$connection); $this->assertOwned($chatbot,$rewrite); $data=$request->validate(['idempotency_key'=>['required','string','max:191']]);
        $result=$runtime->prepareWrite($chatbot,$connection,$rewrite,(string)$data['idempotency_key'],(int)Auth::id());
        return response()->json(['data'=>['rewrite'=>$result['rewrite'],'marketplace_write_proposal'=>$result['proposal']],'approval_token'=>$result['approval_token']],201);
    }
    private function assertOwned(Chatbot $chatbot,object $model): void { $this->assertOwner($chatbot); abort_unless((int)$model->chatbot_id===(int)$chatbot->id && (int)$model->owner_user_id===(int)Auth::id(),404); }
    private function assertOwner(Chatbot $chatbot): void { abort_unless(Auth::id()!==null && (int)$chatbot->user_id===(int)Auth::id(),403); }
}
