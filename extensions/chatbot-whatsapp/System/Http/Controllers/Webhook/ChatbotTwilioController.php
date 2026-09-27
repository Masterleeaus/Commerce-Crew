<?php

namespace App\Extensions\ChatbotWhatsapp\System\Http\Controllers\Webhook;

use App\Extensions\Chatbot\System\Models\ChatbotChannelWebhook;
use App\Extensions\ChatbotWhatsapp\System\Services\Twillio\TwilioConversationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChatbotTwilioController extends Controller
{
    public function __construct(
        public TwilioConversationService $service
    ) {}

    public function handle(
        int $chatbotId,
        int $channelId,
        Request $request
    ) {
        if (! $request->get('SmsSid')) {
            return [
                'status' => false,
            ];
        }

        ChatbotChannelWebhook::query()->create([
            'chatbot_id'         => $chatbotId,
            'chatbot_channel_id' => $channelId,
            'payload'            => $request->all(),
            'created_at'         => now(),
        ]);

        $this->service
            ->setIpAddress()
            ->setChatbotId($chatbotId)
            ->setChannelId($channelId)
            ->setPayload($request->all());

        $conversation = $this->service->storeConversation();

        $chatbot = $this->service->getChatbot();

        $this->service->insertMessage(
            conversation: $conversation,
            message: $request->get('Body') ?? '',
            role: 'user',
            model: $chatbot?->getAttribute('ai_model')
        );

        $this->service->handleWhatsapp();
    }
}
