<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use Closure;

final class CommerceTenantContext
{
    /** @var array<string,mixed> */
    private array $context = [];

    /** @param array<string,mixed> $context */
    public function activate(array $context): void
    {
        $this->context = array_filter($context, static fn ($value): bool => $value !== null);
        config(['chatbot-ecommerce.runtime_tenant' => $this->context]);
    }

    public function clear(): void
    {
        $this->context = [];
        config(['chatbot-ecommerce.runtime_tenant' => []]);
    }

    /** @return array<string,mixed> */
    public function current(): array
    {
        return $this->context;
    }

    /** @template T @param array<string,mixed> $context @param Closure():T $callback @return T */
    public function run(array $context, Closure $callback): mixed
    {
        $previous = $this->context;
        $this->activate($context);
        try {
            return $callback();
        } finally {
            $previous === [] ? $this->clear() : $this->activate($previous);
        }
    }
}
