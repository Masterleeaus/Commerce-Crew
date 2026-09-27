<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Contracts\WorkflowRuntimeInterface;
use App\Extensions\Chatbot\System\Models\ChatbotWorkflowRun;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkflowRuntime implements WorkflowRuntimeInterface
{
    private const TERMINAL = ['completed', 'failed', 'cancelled'];

    public function dispatch(string $workflow, array $context = [], array $options = []): array
    {
        $workflow = trim($workflow);
        if ($workflow === '') {
            throw ValidationException::withMessages(['workflow' => 'A workflow name is required.']);
        }

        $idempotencyKey = $options['idempotency_key'] ?? null;
        $conversationId = $options['conversation_id'] ?? null;

        if ($idempotencyKey) {
            $existing = ChatbotWorkflowRun::query()
                ->where('workflow', $workflow)
                ->where('idempotency_key', $idempotencyKey)
                ->when($conversationId, fn ($query) => $query->where('conversation_id', $conversationId))
                ->first();
            if ($existing) {
                return $existing->toArray();
            }
        }

        $run = ChatbotWorkflowRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'workflow' => $workflow,
            'idempotency_key' => $idempotencyKey,
            'status' => 'queued',
            'attempt' => 1,
            'max_attempts' => max(1, min(25, (int) ($options['max_attempts'] ?? 3))),
            'context' => $context,
            'available_at' => now(),
        ]);

        event('chatbot.workflow.queued', ['run' => $run->toArray()]);

        return $run->toArray();
    }

    public function get(string $runId): array
    {
        return $this->find($runId)->toArray();
    }

    public function resume(string $runId, array $input = []): array
    {
        return DB::transaction(function () use ($runId, $input): array {
            $run = $this->locked($runId);
            $this->assertNotTerminal($run);

            $run->forceFill([
                'status' => 'running',
                'context' => array_replace_recursive($run->context ?? [], $input),
                'started_at' => $run->started_at ?? now(),
                'available_at' => null,
                'locked_at' => null,
                'lock_token' => null,
                'error' => null,
            ])->save();

            event('chatbot.workflow.running', ['run' => $run->fresh()->toArray()]);

            return $run->fresh()->toArray();
        });
    }

    public function complete(string $runId, array $output = []): array
    {
        return $this->finish($runId, 'completed', $output, null);
    }

    public function fail(string $runId, string $error, array $output = []): array
    {
        $error = trim($error);
        if ($error === '') {
            throw ValidationException::withMessages(['error' => 'A workflow failure reason is required.']);
        }

        return $this->finish($runId, 'failed', $output, $error);
    }

    public function retry(string $runId, ?int $delaySeconds = null): array
    {
        return DB::transaction(function () use ($runId, $delaySeconds): array {
            $run = $this->locked($runId);

            if ($run->status !== 'failed') {
                throw ValidationException::withMessages(['status' => 'Only failed workflow runs can be retried.']);
            }
            if ($run->attempt >= $run->max_attempts) {
                throw ValidationException::withMessages(['attempt' => 'The workflow run has reached its maximum attempts.']);
            }

            $delay = $delaySeconds ?? min(3600, 30 * (2 ** max(0, $run->attempt - 1)));
            $run->forceFill([
                'status' => 'queued',
                'attempt' => $run->attempt + 1,
                'available_at' => now()->addSeconds(max(0, $delay)),
                'locked_at' => null,
                'lock_token' => null,
                'error' => null,
                'started_at' => null,
                'finished_at' => null,
            ])->save();

            event('chatbot.workflow.retried', ['run' => $run->fresh()->toArray()]);

            return $run->fresh()->toArray();
        });
    }

    public function cancel(string $runId): void
    {
        DB::transaction(function () use ($runId): void {
            $run = $this->locked($runId);
            if (in_array($run->status, self::TERMINAL, true)) {
                return;
            }

            $run->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'finished_at' => now(),
                'locked_at' => null,
                'lock_token' => null,
            ])->save();

            event('chatbot.workflow.cancelled', ['run' => $run->fresh()->toArray()]);
        });
    }

    public function claimNext(int $staleAfterSeconds = 300): ?ChatbotWorkflowRun
    {
        return DB::transaction(function () use ($staleAfterSeconds): ?ChatbotWorkflowRun {
            $run = ChatbotWorkflowRun::query()
                ->whereIn('status', ['queued', 'running'])
                ->where(function ($query) use ($staleAfterSeconds): void {
                    $query->where(function ($queued): void {
                        $queued->where('status', 'queued')->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()));
                    })->orWhere(function ($running) use ($staleAfterSeconds): void {
                        $running->where('status', 'running')->where('locked_at', '<=', now()->subSeconds(max(1, $staleAfterSeconds)));
                    });
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $run) {
                return null;
            }

            $run->forceFill([
                'status' => 'running',
                'started_at' => $run->started_at ?? now(),
                'locked_at' => now(),
                'lock_token' => (string) Str::uuid(),
            ])->save();

            return $run->fresh();
        });
    }

    private function finish(string $runId, string $status, array $output, ?string $error): array
    {
        return DB::transaction(function () use ($runId, $status, $output, $error): array {
            $run = $this->locked($runId);
            $this->assertNotTerminal($run);

            $run->forceFill([
                'status' => $status,
                'output' => $output,
                'error' => $error,
                'finished_at' => now(),
                'locked_at' => null,
                'lock_token' => null,
            ])->save();

            event("chatbot.workflow.$status", ['run' => $run->fresh()->toArray()]);

            return $run->fresh()->toArray();
        });
    }

    private function find(string $runId): ChatbotWorkflowRun
    {
        $run = ChatbotWorkflowRun::query()->where('uuid', $runId)->first();
        if (! $run) {
            throw (new ModelNotFoundException())->setModel(ChatbotWorkflowRun::class, [$runId]);
        }

        return $run;
    }

    private function locked(string $runId): ChatbotWorkflowRun
    {
        $run = ChatbotWorkflowRun::query()->where('uuid', $runId)->lockForUpdate()->first();
        if (! $run) {
            throw (new ModelNotFoundException())->setModel(ChatbotWorkflowRun::class, [$runId]);
        }

        return $run;
    }

    private function assertNotTerminal(ChatbotWorkflowRun $run): void
    {
        if (in_array($run->status, self::TERMINAL, true)) {
            throw ValidationException::withMessages(['status' => "Workflow run is already {$run->status}."]);
        }
    }
}
