<?php

namespace App\Extensions\ChatbotInstagram\System\Http\Controllers;

use App\Extensions\Chatbot\System\Http\Resources\Admin\ChatbotChannelResource;
use App\Extensions\Chatbot\System\Models\ChatbotChannel;
use App\Extensions\ChatbotInstagram\System\Http\Requests\InstagramChannelStoreRequest;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ChatbotInstagramController extends Controller
{
    public function store(InstagramChannelStoreRequest $request): ChatbotChannelResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $item = ChatbotChannel::query()->create(
            $request->validated()
        );

        return ChatbotChannelResource::make($item)->additional([
            'status'  => 'success',
            'message' => trans('Instagram kanalı başarılı şekilde oluşturuldu.'),
        ]);
    }
}
