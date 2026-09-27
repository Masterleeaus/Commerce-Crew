<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RuntimeReliabilityFilesTest extends TestCase
{
    public function test_reliable_runtime_components_are_packaged(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/System/Services/EventOutboxProcessor.php');
        self::assertFileExists($root . '/System/Console/Commands/ProcessRuntimeOutboxCommand.php');
        self::assertFileExists($root . '/database/migrations/2026_07_22_000003_reliable_runtime_delivery.php');
    }
}
