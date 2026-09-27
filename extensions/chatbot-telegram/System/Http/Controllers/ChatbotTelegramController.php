<?php

namespace App\Extensions\ChatbotTelegram\System\Http\Controllers;

use App\Extensions\Chatbot\System\Helpers\ChatbotHelper;
use App\Extensions\Chatbot\System\Http\Resources\Admin\ChatbotChannelResource;
use App\Extensions\Chatbot\System\Models\ChatbotChannel;
use App\Extensions\ChatbotTelegram\System\Http\Requests\TelegramChannelStoreRequest;
use App\Extensions\ChatbotTelegram\System\Services\Telegram\TelegramService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;

class ChatbotTelegramController extends Controller
{
    public function store(TelegramChannelStoreRequest $request): ChatbotChannelResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        if (! ChatbotHelper::planAllowsChannel('telegram')) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('Your current plan does not allow the Telegram channel.'),
            ], 403);
        }

        $item = ChatbotChannel::query()->create(
            $request->validated()
        );

        try {
            app(TelegramService::class)->setChannel($item)->setWebhook();
        } catch (Exception $exception) {
            $item->delete();

            return response()->json([
                'status'  => 'error',
                'message' => trans('Telegram channel not connected'),
            ]);
        }

        return ChatbotChannelResource::make($item)->additional([
            'status'  => 'success',
            'message' => trans('Chatbot channel successfully created'),
        ]);
    }
}
