<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use JsonException;

final class BnplStateToken
{
    /** @param array<string,mixed> $payload */
    public static function issue(array $payload, string $secret, int $issuedAt, int $ttlSeconds): string
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('A BNPL state secret is required.');
        }

        $payload['iat'] = $issuedAt;
        $payload['exp'] = $issuedAt + max(60, $ttlSeconds);
        $encoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encoded, $secret, true);

        return $encoded . '.' . self::base64UrlEncode($signature);
    }

    /** @return array<string,mixed>|null */
    public static function verify(string $token, string $secret, int $now): ?array
    {
        if ($secret === '' || ! str_contains($token, '.')) {
            return null;
        }

        [$encoded, $signature] = explode('.', $token, 2);
        $expected = self::base64UrlEncode(hash_hmac('sha256', $encoded, $secret, true));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        try {
            $decoded = json_decode(self::base64UrlDecode($encoded), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded) || (int) ($decoded['exp'] ?? 0) < $now) {
            return null;
        }

        return $decoded;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : '';
    }
}
