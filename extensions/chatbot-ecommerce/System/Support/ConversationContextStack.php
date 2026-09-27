<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class ConversationContextStack
{
    /** @param array<string,mixed> $current @param array<string,mixed> $incoming @return array<string,mixed> */
    public static function merge(array $current, array $incoming): array
    {
        $merged = $current;

        foreach ($incoming as $key => $value) {
            if ($key === 'last_filters' && is_array($value)) {
                $merged[$key] = array_merge(is_array($current[$key] ?? null) ? $current[$key] : [], $value);
                continue;
            }

            if ($key === 'current_product_ids') {
                $ids = array_values(array_unique(array_map('intval', is_array($value) ? $value : [])));
                $merged[$key] = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
                continue;
            }

            if ($key === 'pending_actions' && is_array($value)) {
                $existing = is_array($current[$key] ?? null) ? $current[$key] : [];
                $merged[$key] = array_values(array_merge($existing, $value));
                continue;
            }

            $merged[$key] = $value;
        }

        $merged['context_version'] = max(0, (int) ($current['context_version'] ?? 0)) + 1;

        return $merged;
    }

    /** @param array<string,mixed> $context */
    public static function requireProductReference(array $context, int|string|null $explicitProductId): ?int
    {
        $explicit = is_numeric($explicitProductId) ? (int) $explicitProductId : 0;
        if ($explicit > 0) {
            return $explicit;
        }

        $selected = (int) ($context['selected_product_id'] ?? 0);
        if ($selected > 0) {
            return $selected;
        }

        $ids = array_values(array_filter(array_map('intval', (array) ($context['current_product_ids'] ?? [])), static fn (int $id): bool => $id > 0));

        return count($ids) === 1 ? $ids[0] : null;
    }

    /** @param array<string,mixed> $context */
    public static function requireVariantReference(array $context, int|string|null $explicitVariantId): ?int
    {
        $explicit = is_numeric($explicitVariantId) ? (int) $explicitVariantId : 0;
        if ($explicit > 0) {
            return $explicit;
        }

        $selected = (int) ($context['selected_variant_id'] ?? 0);

        return $selected > 0 ? $selected : null;
    }
}
