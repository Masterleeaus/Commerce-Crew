<?php

namespace App\Extensions\ChatbotMessenger\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotChannel;

class MessengerService
{
    public ChatbotChannel $chatbotChannel;

    public function sendText($message, $receiver)
    {
        $access_token = data_get($this->chatbotChannel['credentials'], 'access_token', '');

        $simpleMessengerBot = new \App\Extensions\ChatbotMessenger\System\Helpers\SimpleMessengerBot($access_token);

        $simpleMessengerBot->sendMessage($receiver, $message);
    }

    public function getChatbotChannel(): ChatbotChannel
    {
        return $this->chatbotChannel;
    }

    public function setChatbotChannel(ChatbotChannel $chatbotChannel): self
    {
        $this->chatbotChannel = $chatbotChannel;

        return $this;
    }
}
