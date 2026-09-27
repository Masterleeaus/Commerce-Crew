<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\MarketplaceOrderResource;
use App\Extensions\ChatbotEcommerce\System\Jobs\ImportMarketplaceOrders;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceOrderSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSyncRun;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceOrderImportRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\MarketplaceProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
final class MarketplaceAdminApiController extends Controller
{
    public function connections(Chatbot $chatbot): JsonResponse
    {
        $this->assertOwner($chatbot);
        $rows = MarketplaceConnection::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->orderBy('provider')->get()->map(fn ($c) => $this->connectionData($c));
        return response()->json(['data' => $rows]);
    }
    public function storeConnection(Chatbot $chatbot, Request $request, MarketplaceProviderRegistry $registry): JsonResponse
    {
        $this->assertOwner($chatbot);
        $validated = $request->validate([
            'provider' => ['required','in:amazon,ebay,etsy,generic'], 'name' => ['required','string','max:191'],
            'marketplace' => ['sometimes','nullable','string','max:40'], 'region' => ['sometimes','nullable','string','max:20'],
            'external_account_id' => ['sometimes','nullable','string','max:191'], 'credential_reference' => ['required','string','max:191','regex:/^[A-Za-z0-9._:\/-]+$/'],
            'configuration' => ['sometimes','array'], 'active' => ['sometimes','boolean'], 'write_enabled' => ['sometimes','boolean'], 'metadata' => ['sometimes','array'],
        ]);
        $this->rejectSecretConfiguration((array) ($validated['configuration'] ?? []));
        $validated['chatbot_id'] = (int) $chatbot->getAttribute('id'); $validated['owner_user_id'] = (int) Auth::id();
        $validated['capabilities'] = $registry->allCapabilities($validated['provider']);
        $connection = MarketplaceConnection::query()->create($validated);
        return response()->json(['data' => $this->connectionData($connection)], 201);
    }
    public function updateConnection(Chatbot $chatbot, MarketplaceConnection $connection, Request $request, MarketplaceProviderRegistry $registry): JsonResponse
    {
        $this->assertOwnedConnection($chatbot, $connection);
        $validated = $request->validate([
            'name' => ['sometimes','string','max:191'], 'marketplace' => ['sometimes','nullable','string','max:40'], 'region' => ['sometimes','nullable','string','max:20'],
            'external_account_id' => ['sometimes','nullable','string','max:191'], 'credential_reference' => ['sometimes','string','max:191','regex:/^[A-Za-z0-9._:\/-]+$/'],
            'configuration' => ['sometimes','array'], 'active' => ['sometimes','boolean'], 'write_enabled' => ['sometimes','boolean'], 'metadata' => ['sometimes','array'],
        ]);
        $this->rejectSecretConfiguration((array) ($validated['configuration'] ?? []));
        $validated['capabilities'] = $registry->allCapabilities((string) $connection->provider);
        $connection->forceFill($validated)->save();
        return response()->json(['data' => $this->connectionData($connection->fresh())]);
    }
    public function importOrders(Chatbot $chatbot, MarketplaceConnection $connection, MarketplaceOrderImportRuntime $runtime): JsonResponse
    {
        $this->assertOwnedConnection($chatbot, $connection);
        if (config('chatbot-ecommerce.marketplaces.dispatch_mode', 'sync') === 'async') {
            ImportMarketplaceOrders::dispatch($connection->id);
            return response()->json(['data' => ['status' => 'queued', 'connection_uuid' => $connection->uuid]], 202);
        }
        return response()->json(['data' => $runtime->import($connection)]);
    }
    public function orders(Chatbot $chatbot, Request $request)
    {
        $this->assertOwner($chatbot);
        $query = MarketplaceOrderSnapshot::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->with('lines')->orderByDesc('placed_at');
        foreach (['provider','status','fulfillment_status'] as $filter) { if ($request->filled($filter)) { $query->where($filter, (string) $request->input($filter)); } }
        return MarketplaceOrderResource::collection($query->paginate(min(100, max(1, $request->integer('per_page', 25)))));
    }
    public function syncRuns(Chatbot $chatbot): JsonResponse
    {
        $this->assertOwner($chatbot);
        return response()->json(['data' => MarketplaceSyncRun::query()->where('chatbot_id', (int) $chatbot->getAttribute('id'))->latest('id')->limit(100)->get()]);
    }
    private function rejectSecretConfiguration(array $configuration): void
    {
        $blocked = ['access_token','refresh_token','api_key','secret','client_secret','consumer_key','consumer_secret','password','private_key'];
        $scan = function (array $values) use (&$scan, $blocked): void {
            foreach ($values as $key => $value) {
                if (in_array(strtolower((string) $key), $blocked, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['configuration' => 'Raw marketplace credentials are not accepted. Store them in the credential vault and provide only credential_reference.']);
                }
                if (is_array($value)) { $scan($value); }
            }
        };
        $scan($configuration);
    }

    private function connectionData(MarketplaceConnection $connection): array
    {
        return ['uuid' => $connection->uuid, 'provider' => $connection->provider, 'name' => $connection->name, 'marketplace' => $connection->marketplace, 'region' => $connection->region, 'external_account_id' => $connection->external_account_id, 'credential_reference_hint' => substr((string) $connection->credential_reference, 0, 4) . '…', 'capabilities' => $connection->capabilities ?? [], 'active' => (bool) $connection->active, 'write_enabled' => (bool) ($connection->write_enabled ?? false), 'last_read_at' => $connection->last_read_at?->toIso8601String(), 'last_error' => $connection->last_error];
    }
    private function assertOwnedConnection(Chatbot $chatbot, MarketplaceConnection $connection): void { $this->assertOwner($chatbot); abort_unless((int) $connection->chatbot_id === (int) $chatbot->getAttribute('id'), 404); }
    private function assertOwner(Chatbot $chatbot): void { abort_unless(Auth::id() !== null && (int) $chatbot->getAttribute('user_id') === (int) Auth::id(), 403); }
}
