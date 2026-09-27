<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class PaymentWebhookVerifier
{
    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    public static function verify(
        string $payload,
        ?string $signature,
        string $secret,
        int $timestamp,
        ?int $now = null,
        int $toleranceSeconds = 300,
    ): bool {
        if ($signature === null || $secret === '' || abs(($now ?? time()) - $timestamp) > max(0, $toleranceSeconds)) {
            return false;
        }

        $provided = null;
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 'v1' && is_string($value)) {
                $provided = $value;
                break;
            }
        }

        if ($provided === null) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        return hash_equals($expected, $provided);
    }

    public static function eventKey(string $provider, string $eventId, string $payload): string
    {
        $eventId = trim($eventId);
        $identity = $eventId !== '' ? $eventId : hash('sha256', $payload);
        return hash('sha256', strtolower(trim($provider)) . '|' . $identity);
    }
}
