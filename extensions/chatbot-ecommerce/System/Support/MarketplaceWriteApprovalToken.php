<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;

final class MarketplaceWriteApprovalToken
{
    /** @param array<string,mixed> $payload */
    public static function issue(array $payload, string $secret, int $ttlSeconds = 600, ?int $now = null): string
    {
        if ($secret === '') {
            throw new InvalidArgumentException('Marketplace write approval secret is not configured.');
        }
        $now ??= time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + max(30, $ttlSeconds);
        $payload['jti'] = bin2hex(random_bytes(16));
        $encoded = self::encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $signature = self::encode(hash_hmac('sha256', $encoded, $secret, true));

        return $encoded . '.' . $signature;
    }

    /** @return array<string,mixed> */
    public static function verify(string $token, string $secret, ?int $now = null): array
    {
        if ($secret === '') {
            throw new InvalidArgumentException('Marketplace write approval secret is not configured.');
        }
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if ($encoded === '' || $signature === '') {
            throw new InvalidArgumentException('Malformed marketplace approval token.');
        }
        $expected = self::encode(hash_hmac('sha256', $encoded, $secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Invalid marketplace approval token signature.');
        }
        $payload = json_decode(self::decode($encoded), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid marketplace approval token payload.');
        }
        $now ??= time();
        if ((int) ($payload['exp'] ?? 0) < $now) {
            throw new InvalidArgumentException('Marketplace approval token has expired.');
        }
        foreach (['proposal_uuid', 'action_hash', 'chatbot_id', 'user_id', 'jti'] as $required) {
            if (! isset($payload[$required]) || $payload[$required] === '') {
                throw new InvalidArgumentException("Marketplace approval token is missing {$required}.");
            }
        }

        return $payload;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid marketplace approval token encoding.');
        }

        return $decoded;
    }
}
