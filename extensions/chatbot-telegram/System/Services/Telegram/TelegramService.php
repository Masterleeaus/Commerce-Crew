<?php

namespace App\Extensions\ChatbotTelegram\System\Services\Telegram;

use App\Extensions\Chatbot\System\Models\ChatbotChannel;
use Exception;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    public ChatbotChannel $channel;

    public function sendText($message, $receiver): void
    {
        $token = data_get($this->channel->credentials, 'telegram_token');

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id'    => $receiver,
            'text'       => $message,
        ];

        $url .= '?' . http_build_query($data);

        $http = Http::get($url);

        if ($http->failed()) {
            throw new Exception('Failed to send message: ' . $http->body());
        }

        if (! $http->json('ok')) {
            throw new Exception('Failed to send message: ' . $http->json('description'));
        }
    }

    public function setWebhook(): self
    {
        $wehHookUrl = route('api.v2.chatbot.channel.telegram.post.handle', [
            'chatbotId' => $this->channel->chatbot_id,
            'channelId' => $this->channel->id,
        ]);

        $token = data_get($this->channel->credentials, 'telegram_token');

        $url = "https://api.telegram.org/bot{$token}/setWebhook?url={$wehHookUrl}";

        $http = Http::get($url);

        if ($http->failed()) {
            throw new Exception('Failed to set webhook: ' . $http->body());
        }

        if ($http->json('ok') !== true) {
            throw new Exception('Failed to set webhook: ' . $http->json('description'));
        }

        return $this;
    }

    public function setChannel(ChatbotChannel $channel): TelegramService
    {
        $this->channel = $channel;

        return $this;
    }
}
