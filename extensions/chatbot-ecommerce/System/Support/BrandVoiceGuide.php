<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class BrandVoiceGuide
{
    /** @param array<int,string> $examples @param array<int,string> $tone @param array<int,string> $forbiddenTerms
     * @return array<string,mixed>
     */
    public static function fromExamples(array $examples, array $tone = [], array $forbiddenTerms = [], array $preferredTerms = []): array
    {
        $normalise = static function (array $values): array {
            $values = array_map(static fn ($value): string => trim((string) $value), $values);
            $values = array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
            return $values;
        };
        $guide = [
            'examples' => array_slice($normalise($examples), 0, 20),
            'tone' => array_slice($normalise($tone), 0, 12),
            'preferred_terms' => array_slice($normalise($preferredTerms), 0, 100),
            'forbidden_terms' => array_slice($normalise($forbiddenTerms), 0, 100),
        ];
        $guide['fingerprint'] = hash('sha256', json_encode($guide, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $guide;
    }
}
