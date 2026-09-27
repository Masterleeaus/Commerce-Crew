<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Contracts;

interface WorkflowRuntimeInterface
{
    public function dispatch(string $workflow, array $context = [], array $options = []): array;

    public function get(string $runId): array;

    public function resume(string $runId, array $input = []): array;

    public function complete(string $runId, array $output = []): array;

    public function fail(string $runId, string $error, array $output = []): array;

    public function retry(string $runId, ?int $delaySeconds = null): array;

    public function cancel(string $runId): void;
}
