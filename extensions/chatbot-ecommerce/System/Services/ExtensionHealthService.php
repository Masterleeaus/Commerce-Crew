<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\BnplOffer;
use App\Extensions\ChatbotEcommerce\System\Models\BnplProviderProfile;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationAction;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationThread;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceEscalation;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceLifecycleState;
use App\Extensions\ChatbotEcommerce\System\Models\ProviderCircuitBreaker;
use App\Extensions\ChatbotEcommerce\System\Models\ConversationContext;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\CouponUsage;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryReservation;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceConnection;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSearch;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceSyncRun;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceWriteProposal;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryConflict;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryMapping;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceInventoryScanRun;
use App\Extensions\ChatbotEcommerce\System\Models\MarketplaceRateLimit;
use App\Extensions\ChatbotEcommerce\System\Models\ListingIntelligenceRun;
use App\Extensions\ChatbotEcommerce\System\Models\ListingRewriteProposal;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentIntent;
use App\Extensions\ChatbotEcommerce\System\Models\PaymentWebhookEvent;
use App\Extensions\ChatbotEcommerce\System\Models\PricingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAgreement;
use App\Extensions\ChatbotEcommerce\System\Models\RentalCharge;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPayment;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPaymentAllocation;
use App\Extensions\ChatbotEcommerce\System\Models\RentalReceipt;
use App\Extensions\ChatbotEcommerce\System\Models\ShippingQuote;
use App\Extensions\ChatbotEcommerce\System\Models\UnifiedCommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\OrderException;
use App\Extensions\ChatbotEcommerce\System\Support\BnplStatus;
use App\Extensions\ChatbotEcommerce\System\Support\CartStatus;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutStatus;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryReservationState;
use App\Extensions\ChatbotEcommerce\System\Support\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ExtensionHealthService
{
    public function check(): array
    {
        $checks = [
            'database' => false,
            'configuration' => true,
            'catalogue_tables' => false,
            'inventory_engine_tables' => false,
            'cart_engine_tables' => false,
            'pricing_engine_tables' => false,
            'checkout_engine_tables' => false,
            'shipping_engine_tables' => false,
            'fulfillment_engine_tables' => false,
            'tax_engine_tables' => false,
            'rental_hire_tables' => false,
            'payment_engine_tables' => false,
            'bnpl_engine_tables' => false,
            'conversational_commerce_tables' => false,
            'customer_communications_tables' => false,
            'marketplace_read_tables' => false,
            'marketplace_write_tables' => false,
            'listing_intelligence_tables' => false,
            'inventory_reconciliation_tables' => false,
            'credential_security_tables' => false,
            'reliability_tables' => false,
            'order_workbench_tables' => false,
            'extension_lifecycle_status' => 'enabled',
            'open_provider_circuits' => 0,
            'dead_lettered_payment_webhooks' => 0,
            'marketplace_inventory_open_critical_conflicts' => 0,
            'marketplace_inventory_unverified_mappings' => 0,
            'marketplace_inventory_stale_mappings' => 0,
            'marketplace_inventory_failed_scans' => 0,
            'listing_intelligence_blocked_rewrites' => 0,
            'listing_intelligence_recent_failures' => 0,
            'expired_active_reservations' => 0,
            'expired_coupon_reservations' => 0,
            'expired_mutable_carts' => 0,
            'active_carts_without_uuid' => 0,
            'pricing_snapshots_without_hash' => 0,
            'expired_open_checkouts' => 0,
            'ready_checkouts_without_pricing_hash' => 0,
            'expired_shipping_quotes' => 0,
            'selected_shipping_quotes_without_checkout' => 0,
            'active_tax_rates_without_zone' => 0,
            'ready_checkouts_without_tax_context' => 0,
            'overdue_rental_charges' => 0,
            'received_rental_payments_unallocated' => 0,
            'rental_receipts_without_hash' => 0,
            'active_rental_agreements_without_next_charge' => 0,
            'expired_open_payment_intents' => 0,
            'captured_payments_amount_mismatch' => 0,
            'unprocessed_payment_webhooks' => 0,
            'expired_open_bnpl_offers' => 0,
            'active_bnpl_providers_missing_disclosures' => 0,
            'expired_conversation_contexts' => 0,
            'completed_checkouts_without_orders' => 0,
            'orders_without_items' => 0,
            'support_threads_waiting_handoff' => 0,
            'expired_support_actions' => 0,
            'unresolved_critical_escalations' => 0,
            'active_marketplace_connections_with_errors' => 0,
            'stale_marketplace_searches' => 0,
            'failed_marketplace_sync_runs' => 0,
            'marketplace_writes_blocked_by_conflicts' => 0,
            'marketplace_writes_failed_last_day' => 0,
            'marketplace_rate_limits_active' => 0,
            'unreconciled_unified_orders' => 0,
            'open_order_exceptions' => 0,
            'critical_order_exceptions' => 0,
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
            $checks['credential_security_tables'] = $this->tablesExist(['ext_chatbot_ecommerce_credentials']);
            $checks['reliability_tables'] = $this->tablesExist(['ext_chatbot_ecommerce_lifecycle_states', 'ext_chatbot_provider_circuit_breakers']);
            if ($checks['reliability_tables']) {
                $checks['extension_lifecycle_status'] = (string) (CommerceLifecycleState::query()->where('extension_key', 'chatbot-ecommerce')->value('status') ?: 'enabled');
                $checks['open_provider_circuits'] = ProviderCircuitBreaker::query()->whereIn('state', ['open', 'half_open'])->count();
            }

            $checks['catalogue_tables'] = $this->tablesExist([
                'ext_chatbot_products',
                'ext_chatbot_product_variants',
                'ext_chatbot_inventory',
                'ext_chatbot_carts',
            ]);
            $checks['inventory_engine_tables'] = $this->tablesExist([
                'ext_chatbot_inventory_locations',
                'ext_chatbot_inventory_location_stock',
                'ext_chatbot_inventory_reservations',
                'ext_chatbot_inventory_adjustments',
            ]);
            $checks['cart_engine_tables'] = $this->tablesExist([
                'ext_chatbot_cart_lines',
                'ext_chatbot_cart_operations',
            ]);
            $checks['pricing_engine_tables'] = $this->tablesExist([
                'ext_chatbot_pricing_rules',
                'ext_chatbot_pricing_snapshots',
                'ext_chatbot_coupon_usages',
            ]);

            $checks['checkout_engine_tables'] = $this->tablesExist([
                'ext_chatbot_checkout_sessions',
                'ext_chatbot_checkout_operations',
            ]);

            $checks['shipping_engine_tables'] = $this->tablesExist([
                'ext_chatbot_shipping_zones',
                'ext_chatbot_shipping_methods',
                'ext_chatbot_shipping_quotes',
            ]);
            $checks['fulfillment_engine_tables'] = $this->tablesExist([
                'ext_chatbot_fulfillments',
                'ext_chatbot_fulfillment_items',
            ]);
            $checks['tax_engine_tables'] = $this->tablesExist([
                'ext_chatbot_tax_zones',
                'ext_chatbot_tax_rates',
                'ext_chatbot_tax_exemptions',
            ]);
            $checks['rental_hire_tables'] = $this->tablesExist([
                'ext_chatbot_rental_accounts',
                'ext_chatbot_rental_agreements',
                'ext_chatbot_rental_agreement_rates',
                'ext_chatbot_rental_charges',
                'ext_chatbot_rental_payments',
                'ext_chatbot_rental_payment_allocations',
                'ext_chatbot_rental_adjustments',
                'ext_chatbot_rental_receipts',
                'ext_chatbot_rental_ledger_entries',
            ]);
            $checks['payment_engine_tables'] = $this->tablesExist([
                'ext_chatbot_payment_intents',
                'ext_chatbot_payment_operations',
                'ext_chatbot_payment_refunds',
                'ext_chatbot_payment_webhook_events',
            ]);
            $checks['bnpl_engine_tables'] = $this->tablesExist([
                'ext_chatbot_bnpl_provider_profiles',
                'ext_chatbot_bnpl_offers',
            ]);
            $checks['conversational_commerce_tables'] = $this->tablesExist([
                'ext_chatbot_commerce_contexts',
                'ext_chatbot_commerce_spend_limits',
                'ext_chatbot_commerce_error_lexicon',
                'ext_chatbot_commerce_orders',
                'ext_chatbot_commerce_order_items',
                'ext_chatbot_commerce_order_events',
                'ext_chatbot_commerce_returns',
                'ext_chatbot_commerce_return_items',
                'ext_chatbot_commerce_action_journal',
            ]);
            $checks['customer_communications_tables'] = $this->tablesExist([
                'ext_chatbot_commerce_communication_threads',
                'ext_chatbot_commerce_communication_messages',
                'ext_chatbot_commerce_communication_policies',
                'ext_chatbot_commerce_communication_actions',
                'ext_chatbot_commerce_escalations',
            ]);
            $checks['order_workbench_tables'] = $this->tablesExist([
                'ext_chatbot_unified_orders',
                'ext_chatbot_unified_order_source_snapshots',
                'ext_chatbot_order_settlement_entries',
                'ext_chatbot_order_reconciliations',
                'ext_chatbot_order_exceptions',
                'ext_chatbot_order_exception_events',
                'ext_chatbot_order_workbench_actions',
            ]);
            if ($checks['order_workbench_tables']) {
                $checks['unreconciled_unified_orders'] = UnifiedCommerceOrder::query()->where('reconciliation_status', '!=', 'reconciled')->count();
                $checks['open_order_exceptions'] = OrderException::query()->whereIn('status', ['open', 'acknowledged'])->count();
                $checks['critical_order_exceptions'] = OrderException::query()->whereIn('status', ['open', 'acknowledged'])->where('severity', 'critical')->count();
            }
            $checks['marketplace_read_tables'] = $this->tablesExist([
                'ext_chatbot_marketplace_connections',
                'ext_chatbot_marketplace_searches',
                'ext_chatbot_marketplace_search_results',
                'ext_chatbot_marketplace_listing_snapshots',
                'ext_chatbot_marketplace_order_snapshots',
                'ext_chatbot_marketplace_order_line_snapshots',
                'ext_chatbot_marketplace_sync_cursors',
                'ext_chatbot_marketplace_sync_runs',
            ]);
            if ($checks['marketplace_read_tables']) {
                $checks['active_marketplace_connections_with_errors'] = MarketplaceConnection::query()->where('active', true)->whereNotNull('last_error')->count();
                $checks['stale_marketplace_searches'] = MarketplaceSearch::query()->whereIn('status', ['queued','searching','partial'])->where('expires_at', '<=', now())->count();
                $checks['failed_marketplace_sync_runs'] = MarketplaceSyncRun::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
            }
            $checks['marketplace_write_tables'] = $this->tablesExist([
                'ext_chatbot_marketplace_write_proposals',
                'ext_chatbot_marketplace_write_attempts',
                'ext_chatbot_marketplace_rate_limits',
            ]);
            if ($checks['marketplace_write_tables']) {
                $checks['marketplace_writes_blocked_by_conflicts'] = MarketplaceWriteProposal::query()->where('status', 'conflict_blocked')->count();
                $checks['marketplace_writes_failed_last_day'] = MarketplaceWriteProposal::query()->whereIn('status', ['failed','rollback_failed'])->where('updated_at', '>=', now()->subDay())->count();
                $checks['marketplace_rate_limits_active'] = MarketplaceRateLimit::query()->whereNotNull('blocked_until')->where('blocked_until', '>', now())->count();
            }
            $checks['inventory_reconciliation_tables'] = $this->tablesExist([
                'ext_chatbot_marketplace_inventory_policies',
                'ext_chatbot_marketplace_inventory_mappings',
                'ext_chatbot_marketplace_inventory_scan_runs',
                'ext_chatbot_marketplace_inventory_conflicts',
                'ext_chatbot_marketplace_inventory_conflict_events',
            ]);
            if ($checks['inventory_reconciliation_tables']) {
                $checks['marketplace_inventory_open_critical_conflicts'] = MarketplaceInventoryConflict::query()->whereIn('status', ['open','acknowledged','resolution_prepared'])->where('severity', 'critical')->count();
                $checks['marketplace_inventory_unverified_mappings'] = MarketplaceInventoryMapping::query()->where('active', true)->where('verified', false)->count();
                $checks['marketplace_inventory_stale_mappings'] = MarketplaceInventoryMapping::query()->where('active', true)->where(function ($query): void {
                    $query->whereNull('last_scanned_at')->orWhere('last_scanned_at', '<', now()->subMinutes(15));
                })->count();
                $checks['marketplace_inventory_failed_scans'] = MarketplaceInventoryScanRun::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
            }
            $checks['listing_intelligence_tables'] = $this->tablesExist([
                'ext_chatbot_brand_voice_profiles',
                'ext_chatbot_product_content_profiles',
                'ext_chatbot_listing_compliance_rules',
                'ext_chatbot_listing_intelligence_runs',
                'ext_chatbot_listing_intelligence_findings',
                'ext_chatbot_listing_rewrite_proposals',
            ]);
            if ($checks['listing_intelligence_tables']) {
                $checks['listing_intelligence_blocked_rewrites'] = ListingRewriteProposal::query()->where('status', 'blocked')->count();
                $checks['listing_intelligence_recent_failures'] = ListingIntelligenceRun::query()->where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count();
            }

            if ($checks['inventory_engine_tables']) {
                $checks['expired_active_reservations'] = InventoryReservation::query()
                    ->where('status', InventoryReservationState::ACTIVE)
                    ->where('expires_at', '<=', now())
                    ->count();
            }

            if ($checks['pricing_engine_tables']) {
                $checks['expired_coupon_reservations'] = CouponUsage::query()
                    ->where('status', CouponRuntime::RESERVED)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->count();
                $checks['pricing_snapshots_without_hash'] = PricingSnapshot::query()
                    ->whereNull('snapshot_hash')
                    ->count();
            }

            if ($checks['checkout_engine_tables']) {
                $checks['expired_open_checkouts'] = CheckoutSession::query()
                    ->whereNotIn('status', [CheckoutStatus::COMPLETED, CheckoutStatus::FAILED, CheckoutStatus::CANCELLED, CheckoutStatus::EXPIRED])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->count();
                $checks['ready_checkouts_without_pricing_hash'] = CheckoutSession::query()
                    ->whereIn('status', [CheckoutStatus::READY, CheckoutStatus::PAYMENT_PENDING])
                    ->whereNull('pricing_snapshot_hash')
                    ->count();
            }

            if ($checks['shipping_engine_tables']) {
                $checks['expired_shipping_quotes'] = ShippingQuote::query()
                    ->whereIn('status', ['active', 'selected'])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->count();
                $checks['selected_shipping_quotes_without_checkout'] = ShippingQuote::query()
                    ->where('status', 'selected')
                    ->whereNull('checkout_session_id')
                    ->count();
            }

            if ($checks['tax_engine_tables']) {
                $checks['active_tax_rates_without_zone'] = DB::table('ext_chatbot_tax_rates as rates')
                    ->leftJoin('ext_chatbot_tax_zones as zones', 'zones.id', '=', 'rates.tax_zone_id')
                    ->where('rates.active', true)
                    ->whereNotNull('rates.tax_zone_id')
                    ->whereNull('zones.id')
                    ->count();
            }

            if ($checks['checkout_engine_tables'] && Schema::hasColumn('ext_chatbot_checkout_sessions', 'tax_context_hash')) {
                $checks['ready_checkouts_without_tax_context'] = CheckoutSession::query()
                    ->whereIn('status', [CheckoutStatus::READY, CheckoutStatus::PAYMENT_PENDING])
                    ->whereNull('tax_context_hash')
                    ->count();
            }

            if ($checks['rental_hire_tables']) {
                $checks['overdue_rental_charges'] = RentalCharge::query()
                    ->where('balance_due', '>', 0)
                    ->where('status', '<>', 'void')
                    ->where('due_date', '<', now()->toDateString())
                    ->count();
                $checks['rental_receipts_without_hash'] = RentalReceipt::query()
                    ->whereNull('receipt_hash')
                    ->count();
                $checks['active_rental_agreements_without_next_charge'] = RentalAgreement::query()
                    ->where('status', 'active')
                    ->whereNull('next_charge_date')
                    ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))
                    ->count();

                $receivedPayments = RentalPayment::query()->where('status', 'received')->get(['id', 'amount']);
                foreach ($receivedPayments as $payment) {
                    $allocated = (int) RentalPaymentAllocation::query()
                        ->where('rental_payment_id', $payment->id)
                        ->whereNull('reversed_at')
                        ->sum('amount');
                    if ($allocated < (int) $payment->amount) {
                        $checks['received_rental_payments_unallocated']++;
                    }
                }
            }


            if ($checks['bnpl_engine_tables']) {
                $checks['expired_open_bnpl_offers'] = BnplOffer::query()
                    ->whereIn('status', [BnplStatus::QUOTED, BnplStatus::REQUIRES_ACTION, BnplStatus::APPROVED])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->count();
                $checks['active_bnpl_providers_missing_disclosures'] = BnplProviderProfile::query()
                    ->where('active', true)
                    ->where(function ($query): void {
                        $query->whereNull('licence_reference')
                            ->orWhereNull('terms_url')
                            ->orWhereNull('hardship_url')
                            ->orWhereNull('complaints_url');
                    })
                    ->count();
            }

            if ($checks['payment_engine_tables']) {
                $checks['expired_open_payment_intents'] = PaymentIntent::query()
                    ->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::REQUIRES_ACTION, PaymentStatus::AUTHORIZED])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->count();
                $checks['captured_payments_amount_mismatch'] = PaymentIntent::query()
                    ->whereIn('status', [PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED])
                    ->whereColumn('captured_amount', '>', 'amount')
                    ->count();
                $checks['unprocessed_payment_webhooks'] = PaymentWebhookEvent::query()
                    ->whereIn('status', ['received', 'failed'])
                    ->count();
                $checks['dead_lettered_payment_webhooks'] = PaymentWebhookEvent::query()->where('status', 'dead_lettered')->count();
            }

            if ($checks['conversational_commerce_tables']) {
                $checks['expired_conversation_contexts'] = ConversationContext::query()
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->count();
                $checks['completed_checkouts_without_orders'] = CheckoutSession::query()
                    ->where('status', CheckoutStatus::COMPLETED)
                    ->whereNotIn('id', CommerceOrder::query()->select('checkout_session_id')->whereNotNull('checkout_session_id'))
                    ->count();
                $checks['orders_without_items'] = CommerceOrder::query()
                    ->whereDoesntHave('items')
                    ->count();
            }

            if ($checks['customer_communications_tables']) {
                $checks['support_threads_waiting_handoff'] = CommerceCommunicationThread::query()
                    ->where('status', 'handoff')
                    ->count();
                $checks['expired_support_actions'] = CommerceCommunicationAction::query()
                    ->whereIn('status', ['prepared', 'awaiting_approval', 'ready', 'approved'])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->count();
                $checks['unresolved_critical_escalations'] = CommerceEscalation::query()
                    ->where('severity', 'critical')
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->count();
            }

            if ($checks['cart_engine_tables'] && Schema::hasColumn('ext_chatbot_carts', 'uuid')) {
                $checks['expired_mutable_carts'] = ChatbotCart::query()
                    ->whereIn('status', [CartStatus::ACTIVE, CartStatus::ABANDONED])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->count();
                $checks['active_carts_without_uuid'] = ChatbotCart::query()
                    ->where('status', CartStatus::ACTIVE)
                    ->whereNull('uuid')
                    ->count();
            }
        } catch (\Throwable $exception) {
            $checks['error'] = $exception->getMessage();
        }

        return [
            'ok' => $checks['database']
                && $checks['catalogue_tables']
                && $checks['inventory_engine_tables']
                && $checks['cart_engine_tables']
                && $checks['pricing_engine_tables']
                && $checks['checkout_engine_tables']
                && $checks['shipping_engine_tables']
                && $checks['fulfillment_engine_tables']
                && $checks['tax_engine_tables']
                && $checks['rental_hire_tables']
                && $checks['payment_engine_tables']
                && $checks['bnpl_engine_tables']
                && $checks['conversational_commerce_tables']
                && $checks['customer_communications_tables']
                && $checks['order_workbench_tables'],
            'extension' => 'chatbot-ecommerce',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /** @param list<string> $tables */
    private function tablesExist(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
