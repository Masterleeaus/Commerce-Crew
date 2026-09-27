<?php

namespace App\Extensions\ChatbotInstagram\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotChannel;
use Exception;
use Illuminate\Support\Facades\Http;

class InstagramService
{
    public ChatbotChannel $chatbotChannel;

    public function sendText(string $message, string $receiver): void
    {
        $accessToken = data_get($this->chatbotChannel['credentials'], 'access_token', '');
        $instagramId = data_get($this->chatbotChannel['credentials'], 'instagram_id', '');

        if (! $accessToken || ! $instagramId) {
            throw new Exception('Instagram channel credentials missing.');
        }

        $url = "https://graph.facebook.com/v18.0/{$instagramId}/messages";

        $response = Http::withToken($accessToken)->post($url, [
            'messaging_product' => 'instagram',
            'recipient'         => ['id' => $receiver],
            'message'           => ['text' => $message],
        ]);

        if ($response->failed()) {
            throw new Exception('Instagram message send failed: ' . $response->body());
        }
    }

    public function setChatbotChannel(ChatbotChannel $chatbotChannel): self
    {
        $this->chatbotChannel = $chatbotChannel;

        return $this;
    }
}
