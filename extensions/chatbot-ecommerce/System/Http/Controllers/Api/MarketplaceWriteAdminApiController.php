<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\MarketplaceWriteProposalResource;
use App\Extensions\ChatbotEcommerce\System\Jobs\ExecuteMarketplaceWrite;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceWriteProposal;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceWriteRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class MarketplaceWriteAdminApiController extends Controller
{
    public function index(Chatbot $chatbot, Request $request)
    {
        $this->assertOwner($chatbot);
        $query = MarketplaceWriteProposal::query()
            ->where('chatbot_id', (int) $chatbot->getAttribute('id'))
            ->with('attempts')
            ->latest('id');
        foreach (['provider', 'operation', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->input($filter));
            }
        }

        return MarketplaceWriteProposalResource::collection($query->paginate(min(100, max(1, $request->integer('per_page', 25)))));
    }

    public function show(Chatbot $chatbot, MarketplaceWriteProposal $proposal): MarketplaceWriteProposalResource
    {
        $this->assertOwnedProposal($chatbot, $proposal);
        return new MarketplaceWriteProposalResource($proposal->load('attempts'));
    }

    public function prepare(
        Chatbot $chatbot,
        MarketplaceConnection $connection,
        string $externalListingId,
        Request $request,
        MarketplaceWriteRuntime $runtime
    ): JsonResponse {
        $this->assertOwnedConnection($chatbot, $connection);
        $validated = $request->validate([
            'operation' => ['required', 'in:update_listing,update_price,update_inventory,pause_listing,resume_listing'],
            'changes' => ['sometimes', 'array'],
            'expected_source_hash' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $result = $runtime->prepare(
            $chatbot,
            $connection,
            $externalListingId,
            (string) $validated['operation'],
            (array) ($validated['changes'] ?? []),
            (string) $validated['expected_source_hash'],
            (string) $validated['idempotency_key'],
            (int) Auth::id()
        );
        $resource = (new MarketplaceWriteProposalResource($result['proposal']->load('attempts')))->resolve($request);

        return response()->json(['data' => $resource, 'approval_token' => $result['approval_token']], 201);
    }

    public function approve(
        Chatbot $chatbot,
        MarketplaceWriteProposal $proposal,
        Request $request,
        MarketplaceWriteRuntime $runtime
    ): MarketplaceWriteProposalResource {
        $this->assertOwnedProposal($chatbot, $proposal);
        $validated = $request->validate(['approval_token' => ['required', 'string', 'max:4096']]);
        $approved = $runtime->approve($proposal, (string) $validated['approval_token'], (int) Auth::id());

        return new MarketplaceWriteProposalResource($approved->load('attempts'));
    }

    public function execute(
        Chatbot $chatbot,
        MarketplaceWriteProposal $proposal,
        MarketplaceWriteRuntime $runtime
    ): JsonResponse|MarketplaceWriteProposalResource {
        $this->assertOwnedProposal($chatbot, $proposal);
        if ((string) config('chatbot-ecommerce.marketplaces.write.dispatch_mode', 'sync') === 'async') {
            $queued = $runtime->markQueued($proposal);
            ExecuteMarketplaceWrite::dispatch($queued->id);
            return response()->json(['data' => (new MarketplaceWriteProposalResource($queued))->resolve(request())], 202);
        }

        return new MarketplaceWriteProposalResource($runtime->execute($proposal)->load('attempts'));
    }

    public function rollback(
        Chatbot $chatbot,
        MarketplaceWriteProposal $proposal,
        MarketplaceWriteRuntime $runtime
    ): MarketplaceWriteProposalResource {
        $this->assertOwnedProposal($chatbot, $proposal);
        return new MarketplaceWriteProposalResource($runtime->rollback($proposal, (int) Auth::id())->load('attempts'));
    }

    private function assertOwnedConnection(Chatbot $chatbot, MarketplaceConnection $connection): void
    {
        $this->assertOwner($chatbot);
        abort_unless((int) $connection->chatbot_id === (int) $chatbot->getAttribute('id') && (int) $connection->owner_user_id === (int) Auth::id(), 404);
    }

    private function assertOwnedProposal(Chatbot $chatbot, MarketplaceWriteProposal $proposal): void
    {
        $this->assertOwner($chatbot);
        abort_unless((int) $proposal->chatbot_id === (int) $chatbot->getAttribute('id') && (int) $proposal->owner_user_id === (int) Auth::id(), 404);
    }

    private function assertOwner(Chatbot $chatbot): void
    {
        abort_unless(Auth::id() !== null && (int) $chatbot->getAttribute('user_id') === (int) Auth::id(), 403);
    }
}
