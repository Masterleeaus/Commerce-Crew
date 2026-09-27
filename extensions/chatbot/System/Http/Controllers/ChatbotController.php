<?php

namespace App\Extensions\Chatbot\System\Http\Controllers;

use App\Extensions\Chatbot\System\Http\Requests\ChatbotCustomizeRequest;
use App\Extensions\Chatbot\System\Http\Requests\ChatbotStoreRequest;
use App\Extensions\Chatbot\System\Http\Resources\Admin\ChatbotConversationResource;
use App\Extensions\Chatbot\System\Http\Resources\Admin\ChatbotResource;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Services\ChatbotService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ChatbotController extends Controller
{
    public function __construct(public ChatbotService $service) {}

    public function index(Request $request): View
    {
        if (method_exists(Helper::class, 'appIsDemoForChatbot')) {
            if (Helper::appIsDemoForChatbot()) {
                $this->clearDemoData();
            }
        }

        $externalChatbots = $request->user()->externalChatbots->pluck('id')->toArray();
        $unreadAgentMessagesCount = $this->service->unreadAgentMessagesCount($externalChatbots);
        $unreadAiBotMessagesCount = $this->service->unreadAiBotMessagesCount($externalChatbots);
        $allMessagesCount = $this->service->allMessagesCount($externalChatbots);

        return view('chatbot::index', [
            'chatbots' => $this->service->query()
                ->with('channels:id,chatbot_id,channel')
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate(perPage: 100),
            'avatars'                  => $this->service->avatars(),
            'unreadAgentMessagesCount' => $unreadAgentMessagesCount,
            'unreadAiBotMessagesCount' => $unreadAiBotMessagesCount,
            'allMessagesCount'         => $allMessagesCount,
        ]);
    }

    public function store(ChatbotStoreRequest $request): JsonResponse|ChatbotResource
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('This feature is disabled in Demo version.'),
            ], 403);
        }

        $user = $request->user();
        $plan = $user?->activePlan();
        $chatbotLimit = $plan?->chatbot_limit;

        if (! is_null($chatbotLimit) && $chatbotLimit >= 0) {
            $currentChatbots = $user->externalChatbots()->count();

            if ($currentChatbots >= $chatbotLimit) {
                return response()->json([
                    'type'    => 'error',
                    'message' => __('You have reached the chatbot limit for your current plan.'),
                ], 422);
            }
        }

        $chatbot = $this->service->query()->create($request->validated());

        return ChatbotResource::make($chatbot);
    }

    public function update(ChatbotCustomizeRequest $request): JsonResponse|ChatbotResource
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('This feature is disabled in Demo version.'),
            ], 403);
        }

        $data = $request->validated();

        $chatbot = $this->service->query()->findOrFail($data['id']);

        $this->authorize('update', $chatbot);

        if ($request->file('header_bg_image_blob')) {
            $path = $request->file('header_bg_image_blob')->store('chatbot', 'public');

            $data['header_bg_image'] = '/uploads/' . $path;
        }

        if ($request->file('welcome_bg_image_blob')) {
            $path = $request->file('welcome_bg_image_blob')->store('chatbot', 'public');

            $data['welcome_bg_image'] = '/uploads/' . $path;
        }

        if ($request->file('promo_banner_image_blob')) {
            $path = $request->file('promo_banner_image_blob')->store('chatbot', 'public');

            $data['promo_banner_image'] = '/uploads/' . $path;
        }

        if ($chatbot->getAttribute('is_demo')) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->update($data['id'], $data);

        return ChatbotResource::make($chatbot);
    }

    public function conversations(Request $request): AnonymousResourceCollection
    {
        $chatbots = $request->user()->externalChatbots->pluck('id')->toArray();

        $conversations = $this->service->conversations($chatbots);

        return ChatbotConversationResource::collection($conversations);
    }

    public function conversationsWithPaginate(Request $request): AnonymousResourceCollection
    {
        $chatbots = $request->user()->externalChatbots->pluck('id')->toArray();

        $conversations = $this->service->conversationsWithPaginate($chatbots);

        return ChatbotConversationResource::collection($conversations);
    }

    public function searchConversation(Request $request)
    {
        $chatbots = $request->user()->externalChatbots->pluck('id')->toArray();

        $conversations = $this->service->agentConversationsBySearch($chatbots, $request->search ?? '');

        return ChatbotConversationResource::collection($conversations);
    }

    public function delete(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('This feature is disabled in Demo version.'),
            ], 403);
        }

        $request->validate(['id' => 'required']);

        $chatbot = $this->service->query()->findOrFail($request->get('id'));

        $this->authorize('delete', $chatbot);

        if ($chatbot->getAttribute('is_demo')) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        if ($chatbot->getAttribute('user_id') === Auth::id()) {
            $chatbot->delete();
        } else {
            abort(403);
        }

        return response()->json([
            'message' => 'Chatbot deleted successfully',
            'type'    => 'success',
            'status'  => 200,
        ]);
    }

    public function clearDemoData(): void
    {
        Chatbot::query()->where('is_demo', '=', 0)
            ->where('created_at', '<', now()->subMinutes(30))
            ->delete();
    }
}
