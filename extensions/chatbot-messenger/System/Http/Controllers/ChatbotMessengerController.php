<?php

namespace App\Extensions\ChatbotMessenger\System\Http\Controllers;

use App\Extensions\Chatbot\System\Helpers\ChatbotHelper;
use App\Extensions\Chatbot\System\Http\Resources\Admin\ChatbotChannelResource;
use App\Extensions\Chatbot\System\Models\ChatbotChannel;
use App\Extensions\ChatbotMessenger\System\Http\Requests\MessengerChannelStoreRequest;
use App\Extensions\ChatbotMessenger\System\Services\MessengerService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;

class ChatbotMessengerController extends Controller
{
    public function store(MessengerChannelStoreRequest $request): ChatbotChannelResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        if (! ChatbotHelper::planAllowsChannel('messenger')) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('Your current plan does not allow the Messenger channel.'),
            ], 403);
        }

        $item = ChatbotChannel::query()->create(
            $request->validated()
        );

        try {
            app(MessengerService::class)->setChatbotChannel($item);
        } catch (Exception $exception) {
            $item->delete();

            return response()->json([
                'status'  => 'error',
                'message' => trans('Messenger channel not connected'),
            ]);
        }

        return ChatbotChannelResource::make($item)->additional([
            'status'  => 'success',
            'message' => trans('Messenger channel successfully created'),
        ]);
    }
}
