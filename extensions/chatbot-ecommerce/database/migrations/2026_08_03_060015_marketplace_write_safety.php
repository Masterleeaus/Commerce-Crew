<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ext_chatbot_marketplace_connections')) {
            $addWriteEnabled = ! Schema::hasColumn('ext_chatbot_marketplace_connections', 'write_enabled');
            $addLastWriteAt = ! Schema::hasColumn('ext_chatbot_marketplace_connections', 'last_write_at');
            if ($addWriteEnabled || $addLastWriteAt) {
                Schema::table('ext_chatbot_marketplace_connections', function (Blueprint $table) use ($addWriteEnabled, $addLastWriteAt): void {
                    if ($addWriteEnabled) {
                        $table->boolean('write_enabled')->default(false)->index();
                    }
                    if ($addLastWriteAt) {
                        $table->timestamp('last_write_at')->nullable();
                    }
                });
            }
        }
        if (! Schema::hasTable('ext_chatbot_marketplace_write_proposals')) {
            Schema::create('ext_chatbot_marketplace_write_proposals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->index();
                $table->unsignedBigInteger('owner_user_id')->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
                $table->string('provider', 40)->index();
                $table->string('external_listing_id', 191)->index();
                $table->string('operation', 40)->index();
                $table->string('status', 40)->default('prepared')->index();
                $table->string('idempotency_key', 191);
                $table->string('action_hash', 64)->index();
                $table->string('expected_source_hash', 64)->index();
                $table->string('after_source_hash', 64)->nullable()->index();
                $table->json('requested_changes')->nullable();
                $table->json('before_state');
                $table->json('after_state')->nullable();
                $table->json('rollback_state');
                $table->json('conflict_state')->nullable();
                $table->json('provider_result')->nullable();
                $table->string('approval_token_hash', 64)->nullable();
                $table->json('approval_snapshot')->nullable();
                $table->timestamp('approval_expires_at')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->string('error_hash', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['connection_id', 'idempotency_key'], 'marketplace_write_idempotency_unique');
                $table->index(['connection_id', 'external_listing_id', 'status'], 'marketplace_write_listing_state_index');
            });
        }

        if (! Schema::hasTable('ext_chatbot_marketplace_write_attempts')) {
            Schema::create('ext_chatbot_marketplace_write_attempts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('proposal_id')->index();
                $table->unsignedInteger('attempt_number');
                $table->string('phase', 30)->index();
                $table->string('status', 30)->index();
                $table->string('request_hash', 64)->nullable();
                $table->string('response_hash', 64)->nullable();
                $table->string('provider_request_id', 191)->nullable()->index();
                $table->unsignedInteger('http_status')->nullable();
                $table->unsignedInteger('retry_after_seconds')->nullable();
                $table->string('error_hash', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['proposal_id', 'attempt_number', 'phase'], 'marketplace_write_attempt_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_marketplace_rate_limits')) {
            Schema::create('ext_chatbot_marketplace_rate_limits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('connection_id')->index();
                $table->string('operation', 40)->index();
                $table->timestamp('window_started_at')->index();
                $table->unsignedInteger('request_count')->default(0);
                $table->unsignedInteger('limit')->default(0);
                $table->unsignedInteger('remaining')->nullable();
                $table->timestamp('reset_at')->nullable()->index();
                $table->timestamp('blocked_until')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['connection_id', 'operation'], 'marketplace_rate_limit_unique');
            });
        }
    }

    public function down(): void
    {
        // Marketplace write journals and provider evidence are retained by default for audit and rollback safety.
    }
};
