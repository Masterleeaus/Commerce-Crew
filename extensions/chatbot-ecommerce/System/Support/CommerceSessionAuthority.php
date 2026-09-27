<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;
use RuntimeException;

final class CommerceSessionAuthority
{
    /** @param array<string,mixed> $claims */
    public static function issue(array $claims, string $secret, int $ttlSeconds = 1800, ?int $now = null): string
    {
        self::assertSecret($secret);
        $now ??= time();
        $ttlSeconds = max(60, min($ttlSeconds, 86400));
        $payload = array_replace($claims, [
            'v' => 1,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'jti' => bin2hex(random_bytes(16)),
        ]);
        self::assertClaims($payload);
        $body = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $body, $secret, true));

        return $body . '.' . $signature;
    }

    /** @return array<string,mixed> */
    public static function verify(string $token, string $secret, ?int $now = null): array
    {
        self::assertSecret($secret);
        $parts = explode('.', trim($token));
        if (count($parts) !== 2) {
            throw new RuntimeException('Invalid commerce session authority token.');
        }
        [$body, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $body, $secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid commerce session authority signature.');
        }
        $decoded = json_decode(self::base64UrlDecode($body), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid commerce session authority payload.');
        }
        self::assertClaims($decoded);
        $now ??= time();
        if ((int) $decoded['exp'] < $now) {
            throw new RuntimeException('Commerce session authority token has expired.');
        }
        if ((int) $decoded['iat'] > $now + 60) {
            throw new RuntimeException('Commerce session authority token was issued in the future.');
        }

        return $decoded;
    }

    /** @param array<string,mixed> $claims */
    public static function allows(array $claims, string $capability): bool
    {
        return in_array($capability, array_map('strval', (array) ($claims['capabilities'] ?? [])), true);
    }

    /** @param array<string,mixed> $claims */
    private static function assertClaims(array $claims): void
    {
        foreach (['chatbot_id', 'chatbot_uuid', 'session_id', 'capabilities', 'iat', 'exp'] as $key) {
            if (! array_key_exists($key, $claims)) {
                throw new InvalidArgumentException("Missing commerce session authority claim: {$key}");
            }
        }
        if ((int) $claims['chatbot_id'] <= 0 || trim((string) $claims['chatbot_uuid']) === '') {
            throw new InvalidArgumentException('Invalid chatbot authority claims.');
        }
        if (strlen((string) $claims['session_id']) < 16 || strlen((string) $claims['session_id']) > 191) {
            throw new InvalidArgumentException('Session identifiers must contain between 16 and 191 characters.');
        }
        if (! is_array($claims['capabilities']) || $claims['capabilities'] === []) {
            throw new InvalidArgumentException('At least one session capability is required.');
        }
    }

    private static function assertSecret(string $secret): void
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Commerce session authority secret must contain at least 32 characters.');
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url value.');
        }

        return $decoded;
    }
}
