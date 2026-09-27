<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class ClaimEvidenceValidator
{
    /** @param array<int,array<string,mixed>> $claims @return array<string,mixed> */
    public static function validate(array $claims): array
    {
        $validated = [];
        $supported = 0;
        $unsupported = 0;
        foreach (array_slice($claims, 0, 200) as $claim) {
            $text = trim((string) ($claim['claim'] ?? ''));
            if ($text === '') continue;
            $refs = array_values(array_filter(array_map('strval', (array) ($claim['evidence_refs'] ?? [])), static fn (string $ref): bool => trim($ref) !== ''));
            $status = $refs === [] ? 'unsupported' : 'supported';
            $status === 'supported' ? $supported++ : $unsupported++;
            $validated[] = [
                'claim' => $text,
                'status' => $status,
                'evidence_refs' => array_slice($refs, 0, 50),
                'claim_hash' => hash('sha256', strtolower($text)),
            ];
        }
        return ['claims'=>$validated,'supported_count'=>$supported,'unsupported_count'=>$unsupported,'all_supported'=>$unsupported === 0];
    }
}
