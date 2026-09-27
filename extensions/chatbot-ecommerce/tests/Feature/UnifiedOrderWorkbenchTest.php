<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class UnifiedOrderWorkbenchTest extends TestCase
{
    public function test_unified_order_workbench_files_exist(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'System/Services/UnifiedOrderProjectorRuntime.php',
            'System/Services/OrderSettlementRuntime.php',
            'System/Services/OrderExceptionRuntime.php',
            'System/Services/UnifiedOrderWorkbenchRuntime.php',
            'System/Services/OrderWorkbenchToolRuntime.php',
            'System/Http/Controllers/Api/UnifiedOrderWorkbenchAdminApiController.php',
            'System/Models/UnifiedCommerceOrder.php',
            'System/Models/UnifiedOrderSourceSnapshot.php',
            'System/Models/OrderSettlementEntry.php',
            'System/Models/OrderReconciliation.php',
            'System/Models/OrderException.php',
            'System/Models/OrderExceptionEvent.php',
            'System/Models/OrderWorkbenchAction.php',
            'database/migrations/2026_08_03_060021_unified_orders_settlements_exception_workbench.php',
        ] as $file) {
            self::assertFileExists($root.'/'.$file, $file.' is required');
        }
    }

    public function test_external_orders_remain_authoritative_snapshots(): void
    {
        $root = dirname(__DIR__, 2);
        $projector = file_get_contents($root.'/System/Services/UnifiedOrderProjectorRuntime.php');
        $source = file_get_contents($root.'/System/Support/UnifiedOrderSource.php');

        self::assertStringContainsString("'external_authoritative' => true", $projector);
        self::assertStringContainsString('externalAuthoritative', $projector);
        self::assertStringContainsString('return self::isExternal($source);', $source);
        self::assertStringContainsString('External records remain external snapshots and are never silently converted into native orders.', file_get_contents($root.'/README.md'));
    }

    public function test_immutable_financial_and_source_history_is_enforced(): void
    {
        $root = dirname(__DIR__, 2);

        $phrases = [
            'System/Models/UnifiedOrderSourceSnapshot.php' => 'Unified order source snapshots are immutable.',
            'System/Models/OrderSettlementEntry.php' => 'Settlement source entries are immutable.',
            'System/Models/OrderReconciliation.php' => 'Reconciliation history is immutable.',
            'System/Models/OrderExceptionEvent.php' => 'Order exception events are immutable.',
        ];
        foreach ($phrases as $file => $phrase) {
            $contents = file_get_contents($root.'/'.$file);
            self::assertSame(2, substr_count($contents, $phrase), $file);
        }
    }

    public function test_refund_tool_prepares_approval_required_proposal_only(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root.'/System/Services/UnifiedOrderWorkbenchRuntime.php');
        $actions = file_get_contents($root.'/System/Support/OrderWorkbenchAction.php');
        $tools = file_get_contents($root.'/System/Services/OrderWorkbenchToolRuntime.php');

        self::assertStringContainsString('prepareRefundProposal', $runtime);
        self::assertStringContainsString("'approval_required' => true", $runtime);
        self::assertStringContainsString("['refund_proposal']", $actions);
        self::assertStringContainsString('seller_order_prepare_refund', $tools);
        self::assertStringNotContainsString('executeRefund', $tools);
    }

    public function test_seller_tools_and_thread_linking_are_tenant_scoped(): void
    {
        $root = dirname(__DIR__, 2);
        $tools = file_get_contents($root.'/System/Services/OrderWorkbenchToolRuntime.php');
        $controller = file_get_contents($root.'/System/Http/Controllers/Api/UnifiedOrderWorkbenchAdminApiController.php');

        self::assertStringContainsString('assertSeller', $tools);
        self::assertStringContainsString('seller_order_workbench_list', $tools);
        self::assertStringContainsString('seller_order_workbench_sync', $tools);
        self::assertStringContainsString('seller_order_prepare_customer_contact', $tools);
        self::assertStringContainsString('request_hash', file_get_contents($root.'/System/Services/UnifiedOrderWorkbenchRuntime.php'));
        self::assertStringContainsString('Idempotency key was already used for a different order workbench request.', file_get_contents($root.'/System/Services/UnifiedOrderWorkbenchRuntime.php'));
        self::assertStringContainsString('(int) $thread->chatbot_id === (int) $chatbot->getAttribute(\'id\')', $controller);
        self::assertStringContainsString('(int) $order->chatbot_id === (int) $chatbot->getAttribute(\'id\')', $controller);
    }
}
