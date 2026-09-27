<?php

namespace App\Extensions\ChatbotTelegram\System\Http\Controllers\Webhook;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotChannelWebhook;
use App\Extensions\ChatbotTelegram\System\Services\Telegram\TelegramConversationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChatbotTelegramWebhookController extends Controller
{
    public function __construct(
        public TelegramConversationService $service
    ) {}

    public function handle(
        int $chatbotId,
        int $channelId,
        Request $request
    ) {

        if (! $request->get('update_id') && ! $request->get('message')) {
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

        if ($conversation === null) {
            return;
        }

        /** @var Chatbot $chatbot */
        $chatbot = $this->service->getChatbot();

        $this->service->insertMessage(
            conversation: $conversation,
            message: $request->input('message.text') ?? '',
            role: 'user',
            model: $chatbot->getAttribute('ai_model')
        );

        $this->service->handleTelegram();
    }
}
