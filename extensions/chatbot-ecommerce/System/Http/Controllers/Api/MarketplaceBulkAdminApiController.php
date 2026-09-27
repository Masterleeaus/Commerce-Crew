<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\MarketplaceBulkBatchResource;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceBulkBatch;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceBulkRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

final class MarketplaceBulkAdminApiController extends Controller
{
    public function index(Chatbot $chatbot, Request $request)
    {
        $this->assertOwner($chatbot);
        $query = MarketplaceBulkBatch::query()->where('chatbot_id', $chatbot->getAttribute('id'))->where('owner_user_id', Auth::id())->latest('id');
        foreach (['provider', 'operation', 'status'] as $filter) if ($request->filled($filter)) $query->where($filter, $request->string($filter));
        return MarketplaceBulkBatchResource::collection($query->paginate(min(100, max(1, $request->integer('per_page', 25)))));
    }

    public function show(Chatbot $chatbot, MarketplaceBulkBatch $batch): MarketplaceBulkBatchResource
    {
        $this->assertOwned($chatbot, $batch);
        return new MarketplaceBulkBatchResource($batch->load('items.proposal'));
    }

    public function preview(Chatbot $chatbot, MarketplaceConnection $connection, Request $request, MarketplaceBulkRuntime $runtime): JsonResponse
    {
        $this->assertOwner($chatbot);
        abort_unless((int) $connection->chatbot_id === (int) $chatbot->getAttribute('id') && (int) $connection->owner_user_id === (int) Auth::id(), 404);
        $validated = $request->validate([
            'operation' => ['required', 'in:update_listing,update_price,update_inventory,pause_listing,resume_listing'],
            'filters' => ['sometimes', 'array'],
            'changes' => ['sometimes', 'array'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $result = $runtime->preview($chatbot, $connection, (string) $validated['operation'], (array) ($validated['filters'] ?? []), (array) ($validated['changes'] ?? []), (string) $validated['idempotency_key'], (int) Auth::id());
        return response()->json(['data' => (new MarketplaceBulkBatchResource($result['batch']->load('items.proposal')))->resolve($request), 'approval_token' => $result['approval_token']], 201);
    }

    public function approve(Chatbot $chatbot, MarketplaceBulkBatch $batch, Request $request, MarketplaceBulkRuntime $runtime): MarketplaceBulkBatchResource
    {
        $this->assertOwned($chatbot, $batch);
        $validated = $request->validate(['approval_token' => ['required', 'string', 'max:4096']]);
        return new MarketplaceBulkBatchResource($runtime->approve($batch, (string) $validated['approval_token'], (int) Auth::id())->load('items.proposal'));
    }

    public function execute(Chatbot $chatbot, MarketplaceBulkBatch $batch, MarketplaceBulkRuntime $runtime): MarketplaceBulkBatchResource
    {
        $this->assertOwned($chatbot, $batch);
        return new MarketplaceBulkBatchResource($runtime->execute($batch, (int) Auth::id())->load('items.proposal'));
    }

    public function rollback(Chatbot $chatbot, MarketplaceBulkBatch $batch, MarketplaceBulkRuntime $runtime): MarketplaceBulkBatchResource
    {
        $this->assertOwned($chatbot, $batch);
        return new MarketplaceBulkBatchResource($runtime->rollback($batch, (int) Auth::id())->load('items.proposal'));
    }

    private function assertOwned(Chatbot $chatbot, MarketplaceBulkBatch $batch): void
    {
        $this->assertOwner($chatbot);
        abort_unless((int) $batch->chatbot_id === (int) $chatbot->getAttribute('id') && (int) $batch->owner_user_id === (int) Auth::id(), 404);
    }
    private function assertOwner(Chatbot $chatbot): void
    {
        abort_unless(Auth::id() !== null && (int) $chatbot->getAttribute('user_id') === (int) Auth::id(), 403);
    }
}
