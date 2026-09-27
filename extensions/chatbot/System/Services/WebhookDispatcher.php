<?php

namespace App\Extensions\Chatbot\System\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WebhookDispatcher
{
    /** @throws ConnectionException */
    public function dispatch(string $url, string $event, array $payload, string $secret = ''): array
    {
        $body = [
            'event' => $event,
            'extension' => 'chatbot',
            'payload' => $payload,
            'occurred_at' => now()->toIso8601String(),
        ];
        $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $encoded, $secret);
        $response = Http::asJson()
            ->timeout((int) config('chatbot.runtime.outbox.webhook_timeout_seconds', 10))
            ->withHeaders([
                'X-Chatbot-Event' => $event,
                'X-Chatbot-Signature' => 'sha256=' . $signature,
                'Idempotency-Key' => (string) ($payload['event_uuid'] ?? ''),
            ])
            ->withBody($encoded, 'application/json')
            ->post($url);

        return ['status' => $response->status(), 'successful' => $response->successful()];
    }
}
