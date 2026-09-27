<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\MarketplaceSearchResource;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSearch;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceCardRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceReadRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
final class MarketplaceApiController extends Controller
{
    public function store(Chatbot $chatbot, string $sessionId, Request $request, MarketplaceReadRuntime $runtime): JsonResponse
    {
        $validated = $request->validate(['query' => ['required','string','max:500'], 'filters' => ['sometimes','array'], 'providers' => ['sometimes','array','max:4'], 'providers.*' => ['string','in:amazon,ebay,etsy,generic']]);
        $search = $runtime->createSearch($chatbot, $sessionId, $validated['query'], $validated['filters'] ?? [], $validated['providers'] ?? []);
        return response()->json(['data' => new MarketplaceSearchResource($search), 'ui' => $runtime->card($search)], $search->status === 'queued' ? 202 : 200);
    }
    public function show(Chatbot $chatbot, string $sessionId, MarketplaceSearch $search, MarketplaceReadRuntime $runtime): JsonResponse
    {
        abort_unless((int) $search->chatbot_id === (int) $chatbot->getAttribute('id') && hash_equals((string) $search->session_id, $sessionId), 404);
        $search->load('results');
        return response()->json(['data' => new MarketplaceSearchResource($search), 'ui' => $runtime->card($search)]);
    }
    public function listing(Chatbot $chatbot, string $sessionId, string $provider, string $externalListingId, MarketplaceReadRuntime $runtime, MarketplaceCardRuntime $cards): JsonResponse
    {
        $snapshot = $runtime->listing($chatbot, $provider, $externalListingId);
        abort_unless($snapshot !== null, 404);
        return response()->json(['data' => $snapshot->snapshot, 'ui' => $cards->listing($snapshot)]);
    }
}
