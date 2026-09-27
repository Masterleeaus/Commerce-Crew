<?php
use PHPUnit\Framework\TestCase;
class StructuredActionRuntimeFilesTest extends TestCase {
 public function test_structured_action_runtime_files_are_present(): void {
  $root=dirname(__DIR__,2);
  $this->assertFileExists($root.'/System/Services/StructuredActionRuntime.php');
  $this->assertFileExists($root.'/database/migrations/2026_07_22_000006_harden_structured_actions.php');
  $service=file_get_contents($root.'/System/Services/StructuredActionRuntime.php');
  $this->assertStringContainsString('lockForUpdate', $service);
  $this->assertStringContainsString('idempotency_key', $service);
 }
}
