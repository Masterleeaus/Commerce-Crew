<?php

namespace App\Extensions\ChatbotMessenger\System\Http\Controllers\Webhook;

use App\Extensions\Chatbot\System\Models\ChatbotChannel;
use App\Extensions\Chatbot\System\Models\ChatbotChannelWebhook;
use App\Extensions\ChatbotMessenger\System\Services\MessengerConversationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChatbotMessengerWebhookController extends Controller
{
    public function __construct(
        public MessengerConversationService $service
    ) {}

    public function handle(
        int $chatbotId,
        int $channelId,
        Request $request
    ) {
        $channel = ChatbotChannel::query()->findOrFail($channelId);

        ChatbotChannelWebhook::query()->create([
            'chatbot_id'         => $chatbotId,
            'chatbot_channel_id' => $channelId,
            'payload'            => $request->all(),
            'created_at'         => now(),
        ]);

        $this->verifyWebhook(data_get($channel['credentials'], 'verify_token', ''));

        if (! $request->input('entry.0.messaging.0')) {
            return;
        }

        $this->service
            ->setIpAddress()
            ->setChatbotId($chatbotId)
            ->setChannelId($channelId)
            ->setPayload($request->input('entry.0.messaging.0'));

        $conversation = $this->service->storeConversation();

        /**
         * @var ChatbotChannel $chatbot
         */
        $chatbot = $this->service->getChatbot();

        $this->service->insertMessage(
            conversation: $conversation,
            message: $request->input('entry.0.messaging.0.message.text') ?? '',
            role: 'user',
            model: $chatbot->getAttribute('ai_model')
        );

        $this->service->handle();
    }

    private function verifyWebhook($verifyToken): void
    {
        if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] === $verifyToken) {
            if (isset($_GET['hub_challenge'])) {
                echo $_GET['hub_challenge'];
                exit;
            }
        }
    }
}
