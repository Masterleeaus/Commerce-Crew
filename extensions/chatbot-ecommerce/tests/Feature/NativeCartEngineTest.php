<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class NativeCartEngineTest extends TestCase
{
    public function test_native_cart_engine_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        $required = [
            'System/Services/CartRuntime.php',
            'System/Support/CartStatus.php',
            'System/Support/CartLineKey.php',
            'System/Models/ChatbotCartLine.php',
            'System/Models/CartOperation.php',
            'System/Http/Requests/AddCartLineRequest.php',
            'System/Http/Requests/PutCartLineRequest.php',
            'System/Http/Requests/MergeCartRequest.php',
            'System/Http/Requests/RecoverCartRequest.php',
            'System/Console/Commands/ExpireCarts.php',
            'database/migrations/2026_07_22_060004_native_cart_engine.php',
        ];

        foreach ($required as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }

    public function test_cart_routes_and_compatibility_endpoint_are_registered(): void
    {
        $root = dirname(__DIR__, 2);
        $provider = file_get_contents($root . '/System/ChatbotEcommerceServiceProvider.php');

        self::assertStringContainsString("cart/lines", $provider);
        self::assertStringContainsString("cart/recalculate", $provider);
        self::assertStringContainsString("cart/merge", $provider);
        self::assertStringContainsString("cart/abandon", $provider);
        self::assertStringContainsString("cart/recover", $provider);
        self::assertStringContainsString("cart/line', 'putLine", $provider);
    }
}
