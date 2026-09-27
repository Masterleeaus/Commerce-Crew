<?php

namespace App\Extensions\ChatbotAgent\System\Http\Controllers;

use App\Extensions\Chatbot\System\Http\Resources\Admin\ChatbotConversationResource;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\ChatbotCustomerTag\System\Models\ChatbotCustomerTag;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotCustomerTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureAvailable();

        $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:ext_chatbot_conversations,id'],
        ]);

        $assigned = [];

        if ($request->filled('conversation_id')) {
            $conversation = $this->authorizedConversation((int) $request->integer('conversation_id'));
            $assigned = $conversation->customerTags()->pluck('customer_tag_id')->all();
        }

        $tags = ChatbotCustomerTag::query()
            ->orderBy('tag')
            ->get(['id', 'tag', 'tag_color', 'background_color']);

        return response()->json([
            'status'   => 'success',
            'tags'     => $tags,
            'assigned' => $assigned,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureFeatureAvailable();

        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in Demo version.'),
            ], 403);
        }

        $data = $request->validate([
            'tag'       => ['required', 'string', 'max:255'],
            'tag_color' => ['required', 'string', 'max:50'],
        ]);

        $tag = ChatbotCustomerTag::query()->create([
            'tag'              => $data['tag'],
            'tag_color'        => $data['tag_color'],
            'background_color' => lightenColor($data['tag_color'], 55),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => __('Customer tag created.'),
            'tag'     => $tag,
        ], 201);
    }

    public function assign(Request $request): ChatbotConversationResource|JsonResponse
    {
        $this->ensureFeatureAvailable();

        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in Demo version.'),
            ], 403);
        }

        $data = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:ext_chatbot_conversations,id'],
            'tag_ids'         => ['sometimes', 'array'],
            'tag_ids.*'       => ['integer', 'exists:ext_chatbot_customer_tags,id'],
        ]);

        $conversation = $this->authorizedConversation((int) $data['conversation_id']);

        $conversation->customerTags()->sync($data['tag_ids'] ?? []);

        $conversation->load('customerTags');

        return ChatbotConversationResource::make($conversation)->additional([
            'status'  => 'success',
            'message' => __('Customer tags updated.'),
        ]);
    }

    private function ensureFeatureAvailable(): void
    {
        abort_if(! MarketplaceHelper::isRegistered('chatbot-customer-tag'), 404);
    }

    private function authorizedConversation(int $conversationId): ChatbotConversation
    {
        $conversation = ChatbotConversation::query()
            ->with('chatbot:id,user_id')
            ->findOrFail($conversationId);

        abort_unless($conversation->chatbot?->getAttribute('user_id') === auth()->id(), 403);

        return $conversation;
    }
}
