<?php

namespace App\Extensions\ChatbotVoice\System\Http\Controllers;

use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatbotVoiceEmbbedController extends Controller
{
    /**
     * detail of external voice chatbot information by uuid
     */
    public function index(string|int $uuid): JsonResource
    {
        $ExtVoiceChatbot = ExtVoiceChatbot::where('uuid', $uuid)->first();

        return JsonResource::make($ExtVoiceChatbot);
    }
}
