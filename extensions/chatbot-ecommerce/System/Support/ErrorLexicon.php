<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class ErrorLexicon
{
    /** @param array<string,array<string,mixed>> $entries */
    public function __construct(private readonly array $entries = [])
    {
    }

    /** @return array{message:string,remediation:string|null,retryable:bool,severity:string,provider:string,code:string} */
    public function translate(string $provider, string|int|null $code, ?string $rawMessage = null): array
    {
        $provider = strtolower(trim($provider));
        $code = trim((string) $code);
        $entry = $this->entries[$provider . ':' . $code] ?? $this->entries[$provider . ':*'] ?? null;

        if (is_array($entry)) {
            return [
                'message' => (string) ($entry['message'] ?? 'The commerce provider could not complete that action.'),
                'remediation' => isset($entry['remediation']) ? (string) $entry['remediation'] : null,
                'retryable' => (bool) ($entry['retryable'] ?? false),
                'severity' => (string) ($entry['severity'] ?? 'warning'),
                'provider' => $provider,
                'code' => $code,
            ];
        }

        return [
            'message' => 'The commerce provider could not complete that action.',
            'remediation' => 'Try again shortly or review the provider connection in the seller workspace.',
            'retryable' => true,
            'severity' => 'warning',
            'provider' => $provider,
            'code' => $code,
        ];
    }
}
