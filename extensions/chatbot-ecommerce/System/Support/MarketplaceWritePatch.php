<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use InvalidArgumentException;

final class MarketplaceWritePatch
{
    /** @param array<string,mixed> $changes @return array<string,mixed> */
    public static function sanitize(string $operation, array $changes): array
    {
        $operation = MarketplaceWriteOperation::capability($operation);

        return match ($operation) {
            MarketplaceWriteOperation::UPDATE_PRICE => self::price($changes),
            MarketplaceWriteOperation::UPDATE_INVENTORY => self::inventory($changes),
            MarketplaceWriteOperation::UPDATE_LISTING => self::listing($changes),
            MarketplaceWriteOperation::PAUSE_LISTING,
            MarketplaceWriteOperation::RESUME_LISTING => [],
        };
    }

    /** @param array<string,mixed> $changes @return array{price_amount:int,currency:string} */
    private static function price(array $changes): array
    {
        if (! array_key_exists('price_amount', $changes)) {
            throw new InvalidArgumentException('price_amount is required for a marketplace price update.');
        }
        $amount = filter_var($changes['price_amount'], FILTER_VALIDATE_INT);
        if ($amount === false || $amount < 0) {
            throw new InvalidArgumentException('price_amount must be a non-negative integer in minor currency units.');
        }
        $currency = strtoupper(trim((string) ($changes['currency'] ?? '')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('currency must be a three-letter ISO currency code.');
        }

        return ['price_amount' => (int) $amount, 'currency' => $currency];
    }

    /** @param array<string,mixed> $changes @return array{quantity:int} */
    private static function inventory(array $changes): array
    {
        if (! array_key_exists('quantity', $changes)) {
            throw new InvalidArgumentException('quantity is required for a marketplace inventory update.');
        }
        $quantity = filter_var($changes['quantity'], FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0) {
            throw new InvalidArgumentException('quantity must be a non-negative integer.');
        }

        return ['quantity' => (int) $quantity];
    }

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    private static function listing(array $changes): array
    {
        $result = [];
        if (array_key_exists('title', $changes)) {
            $title = trim((string) $changes['title']);
            if ($title === '' || mb_strlen($title) > 500) {
                throw new InvalidArgumentException('title must contain between 1 and 500 characters.');
            }
            $result['title'] = $title;
        }
        if (array_key_exists('description', $changes)) {
            $description = trim((string) $changes['description']);
            if (mb_strlen($description) > 100000) {
                throw new InvalidArgumentException('description is too long.');
            }
            $result['description'] = $description;
        }
        if (array_key_exists('attributes', $changes)) {
            if (! is_array($changes['attributes'])) {
                throw new InvalidArgumentException('attributes must be an object.');
            }
            $result['attributes'] = $changes['attributes'];
        }
        if ($result === []) {
            throw new InvalidArgumentException('At least one supported listing field is required.');
        }

        return $result;
    }

    /** @param array<string,mixed> $changes */
    public static function hash(string $operation, array $changes): string
    {
        return hash('sha256', json_encode([
            'operation' => MarketplaceWriteOperation::capability($operation),
            'changes' => self::canonicalize($changes),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    private static function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(static fn ($child) => is_array($child) ? self::canonicalize($child) : $child, $item)
                    : self::canonicalize($item);
            }
        }

        return $value;
    }
}
