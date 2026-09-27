<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WorkflowRuntimeFilesTest extends TestCase
{
    public function test_workflow_runtime_hardening_files_are_present(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/database/migrations/2026_07_22_000007_harden_workflow_runtime.php');
        self::assertFileExists($root . '/System/Services/WorkflowRuntime.php');
        self::assertFileExists($root . '/System/Contracts/WorkflowRuntimeInterface.php');

        $runtime = file_get_contents($root . '/System/Services/WorkflowRuntime.php');
        self::assertStringContainsString('idempotency_key', $runtime);
        self::assertStringContainsString('claimNext', $runtime);
        self::assertStringContainsString('max_attempts', $runtime);
        self::assertStringContainsString('lockForUpdate', $runtime);
        self::assertStringContainsString('chatbot.workflow.completed', $runtime);

        $provider = file_get_contents($root . '/System/ChatbotServiceProvider.php');
        self::assertStringContainsString("workflows/{runId}/retry", $provider);
        self::assertStringContainsString("workflows/{runId}/complete", $provider);
    }
}
