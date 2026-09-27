<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_unified_orders')) {
            Schema::create('ext_chatbot_unified_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->string('source_type', 60)->index();
                $table->string('source_scope', 191)->default('native')->index();
                $table->string('source_order_id', 191);
                $table->unsignedBigInteger('native_order_id')->nullable()->index();
                $table->unsignedBigInteger('marketplace_order_snapshot_id')->nullable()->index();
                $table->unsignedBigInteger('connection_id')->nullable()->index();
                $table->boolean('external_authoritative')->default(false)->index();
                $table->string('status', 60)->nullable()->index();
                $table->string('fulfillment_status', 60)->nullable()->index();
                $table->string('payment_status', 60)->nullable()->index();
                $table->char('currency', 3)->default('USD');
                $table->integer('gross_total')->default(0);
                $table->integer('discount_total')->default(0);
                $table->integer('tax_total')->default(0);
                $table->integer('shipping_total')->default(0);
                $table->integer('refund_total')->default(0);
                $table->integer('chargeback_total')->default(0);
                $table->integer('fee_total')->default(0);
                $table->integer('shipping_cost_total')->default(0);
                $table->integer('expected_net_payout')->default(0);
                $table->integer('reported_net_payout')->default(0);
                $table->integer('settlement_variance')->default(0);
                $table->string('reconciliation_status', 40)->default('unreconciled')->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('buyer_display_name')->nullable();
                $table->json('shipping_address')->nullable();
                $table->json('source_snapshot')->nullable();
                $table->string('source_hash', 64)->index();
                $table->timestamp('placed_at')->nullable()->index();
                $table->timestamp('source_updated_at')->nullable();
                $table->timestamp('last_projected_at')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'source_type', 'source_scope', 'source_order_id'], 'unified_order_source_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_unified_order_source_snapshots')) {
            Schema::create('ext_chatbot_unified_order_source_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('unified_order_id')->index();
                $table->unsignedInteger('sequence')->default(1);
                $table->string('source_hash', 64)->index();
                $table->json('snapshot');
                $table->timestamp('captured_at')->index();
                $table->timestamps();
                $table->unique(['unified_order_id', 'source_hash'], 'unified_order_snapshot_hash_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_order_settlement_entries')) {
            Schema::create('ext_chatbot_order_settlement_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('unified_order_id')->index();
                $table->string('provider', 60)->index();
                $table->string('source_scope', 191)->index();
                $table->string('external_entry_id', 191);
                $table->string('source_batch_id', 191)->nullable()->index();
                $table->string('entry_type', 60)->index();
                $table->integer('amount');
                $table->char('currency', 3);
                $table->string('source_hash', 64)->index();
                $table->json('source_snapshot');
                $table->timestamp('occurred_at')->nullable()->index();
                $table->timestamp('imported_at')->index();
                $table->timestamps();
                $table->unique(['chatbot_id', 'provider', 'source_scope', 'external_entry_id'], 'order_settlement_provider_scope_entry_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_order_reconciliations')) {
            Schema::create('ext_chatbot_order_reconciliations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('unified_order_id')->index();
                $table->string('status', 40)->index();
                $table->integer('gross_amount')->default(0);
                $table->integer('fee_amount')->default(0);
                $table->integer('tax_withheld_amount')->default(0);
                $table->integer('shipping_cost_amount')->default(0);
                $table->integer('refund_amount')->default(0);
                $table->integer('chargeback_amount')->default(0);
                $table->integer('adjustment_amount')->default(0);
                $table->integer('expected_net_amount')->default(0);
                $table->integer('reported_net_amount')->default(0);
                $table->integer('variance_amount')->default(0);
                $table->string('calculation_hash', 64)->index();
                $table->json('evidence')->nullable();
                $table->timestamp('reconciled_at')->index();
                $table->timestamps();
                $table->unique(['unified_order_id', 'calculation_hash'], 'order_reconciliation_hash_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_order_exceptions')) {
            Schema::create('ext_chatbot_order_exceptions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('unified_order_id')->index();
                $table->string('exception_type', 80)->index();
                $table->string('fingerprint', 64);
                $table->string('severity', 30)->index();
                $table->string('status', 40)->default('open')->index();
                $table->string('title');
                $table->text('summary');
                $table->json('evidence')->nullable();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->unsignedBigInteger('acknowledged_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('resolved_by_user_id')->nullable()->index();
                $table->timestamp('first_detected_at')->index();
                $table->timestamp('last_detected_at')->index();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['unified_order_id', 'fingerprint'], 'order_exception_fingerprint_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_order_exception_events')) {
            Schema::create('ext_chatbot_order_exception_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('exception_id')->index();
                $table->string('event_type', 60)->index();
                $table->string('actor_type', 40)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable()->index();
                $table->json('before_state')->nullable();
                $table->json('after_state')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_order_workbench_actions')) {
            Schema::create('ext_chatbot_order_workbench_actions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('unified_order_id')->index();
                $table->unsignedBigInteger('exception_id')->nullable()->index();
                $table->unsignedBigInteger('communication_thread_id')->nullable()->index();
                $table->string('action_type', 80)->index();
                $table->string('status', 40)->default('prepared')->index();
                $table->string('idempotency_key', 191);
                $table->char('request_hash', 64)->index();
                $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_to_user_id')->nullable()->index();
                $table->integer('amount')->nullable();
                $table->char('currency', 3)->nullable();
                $table->string('source_hash', 64)->nullable();
                $table->json('proposal')->nullable();
                $table->json('result')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('executed_at')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'idempotency_key'], 'order_workbench_action_idempotency_unique');
            });
        }

        if (Schema::hasTable('ext_chatbot_commerce_communication_threads') && ! Schema::hasColumn('ext_chatbot_commerce_communication_threads', 'unified_order_id')) {
            Schema::table('ext_chatbot_commerce_communication_threads', function (Blueprint $table): void {
                $table->unsignedBigInteger('unified_order_id')->nullable()->index()->after('customer_identity_id');
            });
        }
    }

    public function down(): void
    {
        // Unified order, settlement, exception and audit history are preserved during rollback.
    }
};
