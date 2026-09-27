<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SecureAttachmentRuntimeFilesTest extends TestCase
{
    public function test_secure_attachment_runtime_is_packaged(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/System/Services/AttachmentRuntime.php');
        self::assertFileExists($root . '/database/migrations/2026_07_22_000004_secure_attachment_runtime.php');

        $config = require $root . '/config/chatbot.php';
        self::assertArrayHasKey('attachments', $config);
        self::assertGreaterThan(0, $config['attachments']['max_size_kb']);
        self::assertNotEmpty($config['attachments']['blocked_extensions']);
    }
}
