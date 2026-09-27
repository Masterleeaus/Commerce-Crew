<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CommerceUiFallback
{
    /** @param array<int,array<string,mixed>> $items */
    public static function numberedChoices(array $items, string $replyVerb = 'Reply'): string
    {
        $lines = [];
        foreach (array_values($items) as $index => $item) {
            $number = $index + 1;
            $title = trim((string) ($item['title'] ?? 'Option ' . $number));
            $price = trim((string) ($item['price'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            $suffix = implode(' — ', array_values(array_filter([$price, $url], static fn (string $value): bool => $value !== '')));
            $lines[] = sprintf('%s %d for %s%s', $replyVerb, $number, $title, $suffix !== '' ? ' — ' . $suffix : '');
        }

        return implode("\n", $lines);
    }
}
