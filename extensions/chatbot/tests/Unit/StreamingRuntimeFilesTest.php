<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StreamingRuntimeFilesTest extends TestCase
{
    public function test_streaming_runtime_replay_files_are_present(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/database/migrations/2026_07_22_000008_harden_streaming_runtime.php');
        self::assertFileExists($root . '/System/Models/ChatbotStreamEvent.php');
        self::assertFileExists($root . '/System/Services/StreamingRuntime.php');

        $runtime = file_get_contents($root . '/System/Services/StreamingRuntime.php');
        self::assertStringContainsString('function replay(', $runtime);
        self::assertStringContainsString('next_cursor', $runtime);
        self::assertStringContainsString('event_uuid', $runtime);
        self::assertStringContainsString('function prune(', $runtime);

        $provider = file_get_contents($root . '/System/ChatbotServiceProvider.php');
        self::assertStringContainsString("conversations/{conversation}/events", $provider);
    }
}
