<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_ecommerce_lifecycle_states')) {
            Schema::create('ext_chatbot_ecommerce_lifecycle_states', function (Blueprint $table): void {
                $table->id();
                $table->string('extension_key', 120)->unique();
                $table->string('status', 40)->default('enabled')->index();
                $table->string('installed_version', 40)->nullable();
                $table->boolean('preserve_data')->default(true);
                $table->text('reason')->nullable();
                $table->timestamp('enabled_at')->nullable();
                $table->timestamp('disabled_at')->nullable();
                $table->timestamp('uninstall_started_at')->nullable();
                $table->timestamp('uninstalled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_provider_circuit_breakers')) {
            Schema::create('ext_chatbot_provider_circuit_breakers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('provider', 100)->index();
                $table->string('operation', 120)->index();
                $table->string('state', 30)->default('closed')->index();
                $table->unsignedInteger('failure_count')->default(0);
                $table->string('last_failure_hash', 64)->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('opened_until')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'provider', 'operation'], 'commerce_circuit_tenant_provider_operation_unique');
            });
        }

        if (Schema::hasTable('ext_chatbot_payment_webhook_events')) {
            Schema::table('ext_chatbot_payment_webhook_events', function (Blueprint $table): void {
                if (! Schema::hasColumn('ext_chatbot_payment_webhook_events', 'queued_at')) $table->timestamp('queued_at')->nullable()->index();
                if (! Schema::hasColumn('ext_chatbot_payment_webhook_events', 'processing_started_at')) $table->timestamp('processing_started_at')->nullable();
                if (! Schema::hasColumn('ext_chatbot_payment_webhook_events', 'last_attempt_at')) $table->timestamp('last_attempt_at')->nullable();
                if (! Schema::hasColumn('ext_chatbot_payment_webhook_events', 'next_attempt_at')) $table->timestamp('next_attempt_at')->nullable()->index();
                if (! Schema::hasColumn('ext_chatbot_payment_webhook_events', 'dead_lettered_at')) $table->timestamp('dead_lettered_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Reliability and audit records are preserved during rollback.
    }
};
