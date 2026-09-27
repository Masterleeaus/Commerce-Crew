<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_marketplace_inventory_policies')) {
            Schema::create('ext_chatbot_marketplace_inventory_policies', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->nullable()->index();
                $table->unsignedBigInteger('owner_user_id')->index();
                $table->string('source_of_truth', 30)->default('internal')->index();
                $table->string('resolution_mode', 30)->default('suggest_only')->index();
                $table->string('allocation_mode', 30)->default('equal_share');
                $table->unsignedInteger('allocation_bps')->default(10000);
                $table->integer('buffer_quantity')->default(0);
                $table->integer('minimum_quantity')->default(0);
                $table->integer('maximum_quantity')->nullable();
                $table->unsignedInteger('maximum_sync_age_seconds')->default(300);
                $table->unsignedInteger('quantity_tolerance')->default(0);
                $table->unsignedInteger('maximum_auto_delta')->default(0);
                $table->boolean('require_fresh_snapshot')->default(true);
                $table->boolean('auto_map_by_sku')->default(true);
                $table->boolean('active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'connection_id'], 'marketplace_inventory_policy_scope_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_marketplace_inventory_mappings')) {
            Schema::create('ext_chatbot_marketplace_inventory_mappings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->index();
                $table->unsignedBigInteger('listing_snapshot_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->string('external_listing_id', 191)->index();
                $table->string('sku', 191)->nullable()->index();
                $table->string('allocation_mode', 30)->nullable();
                $table->unsignedInteger('allocation_bps')->nullable();
                $table->integer('buffer_quantity')->nullable();
                $table->integer('minimum_quantity')->nullable();
                $table->integer('maximum_quantity')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->boolean('verified')->default(false)->index();
                $table->integer('last_external_quantity')->nullable();
                $table->integer('last_target_quantity')->nullable();
                $table->string('last_source_hash', 64)->nullable()->index();
                $table->timestamp('last_scanned_at')->nullable()->index();
                $table->timestamp('last_in_sync_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['connection_id', 'external_listing_id'], 'marketplace_inventory_mapping_listing_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_marketplace_inventory_scan_runs')) {
            Schema::create('ext_chatbot_marketplace_inventory_scan_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->nullable()->index();
                $table->unsignedBigInteger('owner_user_id')->nullable()->index();
                $table->string('status', 30)->default('queued')->index();
                $table->unsignedInteger('mapping_count')->default(0);
                $table->unsignedInteger('in_sync_count')->default(0);
                $table->unsignedInteger('conflict_count')->default(0);
                $table->unsignedInteger('critical_count')->default(0);
                $table->unsignedInteger('corrections_prepared')->default(0);
                $table->string('failure_hash', 64)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_marketplace_inventory_conflicts')) {
            Schema::create('ext_chatbot_marketplace_inventory_conflicts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->index();
                $table->unsignedBigInteger('mapping_id')->index();
                $table->unsignedBigInteger('scan_run_id')->nullable()->index();
                $table->unsignedBigInteger('write_proposal_id')->nullable()->index();
                $table->string('provider', 40)->index();
                $table->string('external_listing_id', 191)->index();
                $table->string('code', 60)->index();
                $table->string('severity', 20)->index();
                $table->string('status', 30)->default('open')->index();
                $table->integer('canonical_quantity')->default(0);
                $table->integer('target_quantity')->default(0);
                $table->integer('external_quantity')->default(0);
                $table->integer('delta')->default(0);
                $table->integer('oversell_exposure')->default(0);
                $table->unsignedInteger('canonical_version')->default(0);
                $table->string('source_hash', 64)->nullable()->index();
                $table->timestamp('observed_at')->nullable();
                $table->timestamp('first_detected_at')->index();
                $table->timestamp('last_detected_at')->index();
                $table->unsignedBigInteger('acknowledged_by_user_id')->nullable()->index();
                $table->timestamp('acknowledged_at')->nullable();
                $table->unsignedBigInteger('ignored_by_user_id')->nullable()->index();
                $table->timestamp('ignored_at')->nullable();
                $table->timestamp('ignored_until')->nullable()->index();
                $table->text('ignore_reason')->nullable();
                $table->unsignedBigInteger('resolved_by_user_id')->nullable()->index();
                $table->timestamp('resolved_at')->nullable();
                $table->string('resolution_mode', 30)->nullable();
                $table->json('assessment');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['mapping_id', 'status', 'code'], 'marketplace_inventory_conflict_active_index');
            });
        }

        if (! Schema::hasTable('ext_chatbot_marketplace_inventory_conflict_events')) {
            Schema::create('ext_chatbot_marketplace_inventory_conflict_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('conflict_id')->index();
                $table->string('event_type', 40)->index();
                $table->string('actor_type', 20)->default('system');
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('status_before', 30)->nullable();
                $table->string('status_after', 30)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Reconciliation policies, mappings, conflicts, and events are retained for audit and recovery safety.
    }
};
