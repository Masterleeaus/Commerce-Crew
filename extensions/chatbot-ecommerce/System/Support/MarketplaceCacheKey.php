<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class MarketplaceCacheKey
{
    /** @param array<string,mixed> $filters @param array<int,string> $providers */
    public static function make(int $chatbotId, string $query, array $filters, array $providers): string
    {
        ksort($filters);
        $providers = array_values(array_unique(array_map('strtolower', $providers)));
        sort($providers);

        return hash('sha256', json_encode([
            'chatbot_id' => $chatbotId,
            'query' => strtolower(trim(preg_replace('/\s+/', ' ', $query) ?? $query)),
            'filters' => $filters,
            'providers' => $providers,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
