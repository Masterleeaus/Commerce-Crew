<?php

namespace App\Extensions\ChatbotMessenger\System\Helpers;

class SimpleMessengerBot
{
    private string $pageAccessToken;

    public function __construct($pageAccessToken)
    {
        $this->pageAccessToken = $pageAccessToken;
    }

    public function sendMessage($userId, $message)
    {
        $url = 'https://graph.facebook.com/v18.0/me/messages';

        $data = [
            'recipient' => ['id' => $userId],
            'message'   => ['text' => $message],
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL        => $url,
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->pageAccessToken,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $httpCode === 200;
    }

    public function verifyWebhook($verifyToken)
    {
        if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] === $verifyToken) {
            echo $_GET['hub_challenge'];
            exit;
        }
    }

    public function getIncomingMessage()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (isset($data['entry'][0]['messaging'][0]['message'])) {
            $messaging = $data['entry'][0]['messaging'][0];

            return [
                'user_id' => $messaging['sender']['id'],
                'message' => $messaging['message']['text'] ?? '',
            ];
        }

        return null;
    }
}
