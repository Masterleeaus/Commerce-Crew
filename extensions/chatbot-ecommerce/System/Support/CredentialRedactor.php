<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CredentialRedactor
{
    /** @var list<string> */
    private const SECRET_FRAGMENTS = [
        'access_token', 'refresh_token', 'consumer_key', 'consumer_secret', 'api_key',
        'client_secret', 'private_key', 'password', 'credential', 'secret',
    ];

    /** @param array<string|int,mixed> $payload @return array<string|int,mixed> */
    public static function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (self::isSecretKey((string) $key)) {
                $payload[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $payload[$key] = self::redact($value);
            }
        }

        return $payload;
    }

    /** @param array<string|int,mixed> $payload */
    public static function containsSecretKey(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if (self::isSecretKey((string) $key)) {
                return true;
            }
            if (is_array($value) && self::containsSecretKey($value)) {
                return true;
            }
        }

        return false;
    }

    public static function isSecretKey(string $key): bool
    {
        $key = strtolower(trim($key));
        foreach (self::SECRET_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
