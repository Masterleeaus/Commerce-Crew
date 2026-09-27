<?php

namespace App\Extensions\ChatbotVoice\System\Http\Controllers;

use App\Extensions\ChatbotVoice\System\Http\Requests\AvatarRequest;
use App\Extensions\ChatbotVoice\System\Http\Resources\ChatbotAvatarResource;
use App\Extensions\ChatbotVoice\System\Models\ExtVoicechatbotAvatar;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class AvatarController extends Controller
{
    /**
     * upload custom avatar for voice chatbot
     */
    public function __invoke(AvatarRequest $request): JsonResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $file = $request->file('avatar')?->store('avatars', ['disk' => 'public']);

        $chatbotAvatar = ExtVoicechatbotAvatar::query()->create([
            'user_id' => $request->user()->getAttribute('id'),
            'avatar'  => 'uploads/' . $file,
        ]);

        return ChatbotAvatarResource::make($chatbotAvatar);
    }
}
