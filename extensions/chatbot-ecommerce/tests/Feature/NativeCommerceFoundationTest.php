<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class NativeCommerceFoundationTest extends TestCase
{
    public function test_native_commerce_foundation_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        $required = [
            'System/Contracts/CommerceProvider.php',
            'System/Providers/InternalCommerceProvider.php',
            'System/Services/CartRuntime.php',
            'System/Services/PricingRuntime.php',
            'System/Http/Controllers/Api/NativeCommerceApiController.php',
            'database/migrations/2026_07_22_060002_native_commerce_foundation.php',
            'index.json',
        ];

        foreach ($required as $file) {
            self::assertFileExists($root . '/' . $file, $file . ' is required');
        }
    }
}
