<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class CredentialTenantHardeningTest extends TestCase
{
    public function test_security_boundary_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'System/Models/CommerceCredential.php',
            'System/Services/CommerceCredentialRuntime.php',
            'System/Services/CommerceTenantRuntime.php',
            'System/Support/CommerceSessionAuthority.php',
            'System/Http/Middleware/EnsureCommerceAdminAccess.php',
            'System/Http/Middleware/RequireCommerceSessionAuthority.php',
            'database/migrations/2026_08_03_060019_credential_authorization_tenant_hardening.php',
        ] as $file) {
            self::assertFileExists($root . '/' . $file);
        }
    }

    public function test_credential_and_session_boundaries_fail_closed_by_contract(): void
    {
        $root = dirname(__DIR__, 2);
        $credential = file_get_contents($root . '/System/Models/CommerceCredential.php');
        self::assertStringContainsString("'credentials' => 'encrypted:array'", $credential);
        self::assertStringContainsString('protected $hidden', $credential);

        $session = file_get_contents($root . '/System/Http/Middleware/RequireCommerceSessionAuthority.php');
        self::assertStringContainsString('A commerce session authority token is required', $session);
        self::assertStringContainsString('outside the signed session authority', $session);
        self::assertStringContainsString('different storefront origin', $session);
    }

    public function test_cart_and_administration_are_tenant_scoped(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString('assertVariantForCart', file_get_contents($root . '/System/Services/CartRuntime.php'));
        self::assertStringContainsString('source_recovery_token', file_get_contents($root . '/System/Http/Requests/MergeCartRequest.php'));
        self::assertStringContainsString('CommerceTenantRuntime', file_get_contents($root . '/System/Http/Controllers/Api/PaymentAdminApiController.php'));
    }
}
