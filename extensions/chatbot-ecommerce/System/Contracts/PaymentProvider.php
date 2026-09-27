<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Contracts;

interface PaymentProvider
{
    public function name(): string;

    public function supports(string $method): bool;

    /** @return array<string,mixed> */
    public function createIntent(array $context): array;

    /** @return array<string,mixed> */
    public function authorize(array $context): array;

    /** @return array<string,mixed> */
    public function capture(array $context): array;

    /** @return array<string,mixed> */
    public function cancel(array $context): array;

    /** @return array<string,mixed> */
    public function refund(array $context): array;

    /** @return array<string,mixed> */
    public function retrieve(array $context): array;

    public function verifyWebhook(string $payload, ?string $signature, ?string $timestamp): bool;

    /** @return array<string,mixed> */
    public function parseWebhook(array $payload): array;
}
