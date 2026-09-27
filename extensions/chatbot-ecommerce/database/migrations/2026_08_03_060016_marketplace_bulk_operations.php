<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_marketplace_bulk_batches')) {
            Schema::create('ext_chatbot_marketplace_bulk_batches', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->index();
                $table->unsignedBigInteger('owner_user_id')->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
                $table->string('provider', 40)->index();
                $table->string('operation', 40)->index();
                $table->string('status', 40)->default('draft')->index();
                $table->string('idempotency_key', 191);
                $table->string('batch_hash', 64)->index();
                $table->json('selection_filters')->nullable();
                $table->json('change_template')->nullable();
                $table->json('impact_summary')->nullable();
                $table->unsignedInteger('selected_count')->default(0);
                $table->unsignedInteger('eligible_count')->default(0);
                $table->unsignedInteger('blocked_count')->default(0);
                $table->unsignedInteger('succeeded_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('rolled_back_count')->default(0);
                $table->string('approval_token_hash', 64)->nullable();
                $table->timestamp('approval_expires_at')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['connection_id', 'idempotency_key'], 'marketplace_bulk_idempotency_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_marketplace_bulk_items')) {
            Schema::create('ext_chatbot_marketplace_bulk_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('batch_id')->index();
                $table->unsignedBigInteger('listing_snapshot_id')->index();
                $table->unsignedBigInteger('proposal_id')->nullable()->index();
                $table->string('external_listing_id', 191)->index();
                $table->string('status', 40)->default('pending')->index();
                $table->string('expected_source_hash', 64)->index();
                $table->json('before_state')->nullable();
                $table->json('proposed_changes')->nullable();
                $table->json('impact')->nullable();
                $table->json('conflicts')->nullable();
                $table->string('error_hash', 64)->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['batch_id', 'external_listing_id'], 'marketplace_bulk_listing_unique');
            });
        }
    }

    public function down(): void
    {
        // Bulk write journals are retained by default for audit and rollback safety.
    }
};
