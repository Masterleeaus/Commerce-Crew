<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_payment_intents')) {
            Schema::create('ext_chatbot_payment_intents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('checkout_session_id')->nullable()->index();
                $table->unsignedBigInteger('rental_payment_id')->nullable()->index();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('provider', 100)->default('internal')->index();
                $table->string('method', 80)->index();
                $table->string('status', 40)->default('pending')->index();
                $table->integer('amount');
                $table->integer('authorized_amount')->default(0);
                $table->integer('captured_amount')->default(0);
                $table->integer('refunded_amount')->default(0);
                $table->char('currency', 3)->index();
                $table->string('idempotency_key', 191)->unique();
                $table->string('request_hash', 64);
                $table->string('provider_payment_id', 191)->nullable()->index();
                $table->text('payment_url')->nullable();
                $table->json('instructions')->nullable();
                $table->json('provider_payload')->nullable();
                $table->string('failure_code', 100)->nullable()->index();
                $table->text('failure_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('authorized_at')->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();
                $table->index(['provider', 'provider_payment_id'], 'payment_intent_provider_reference_idx');
                $table->index(['status', 'expires_at'], 'payment_intent_status_expiry_idx');
            });
        }

        if (! Schema::hasTable('ext_chatbot_payment_operations')) {
            Schema::create('ext_chatbot_payment_operations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('payment_intent_id')->index();
                $table->string('operation', 60)->index();
                $table->string('idempotency_key', 191);
                $table->string('request_hash', 64);
                $table->integer('amount')->nullable();
                $table->string('status', 40)->default('succeeded')->index();
                $table->string('provider_operation_id', 191)->nullable()->index();
                $table->json('response')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['payment_intent_id', 'idempotency_key'], 'payment_operation_intent_idem_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_payment_refunds')) {
            Schema::create('ext_chatbot_payment_refunds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('payment_intent_id')->index();
                $table->integer('amount');
                $table->char('currency', 3);
                $table->string('status', 40)->default('pending')->index();
                $table->string('reason', 500)->nullable();
                $table->string('idempotency_key', 191)->unique();
                $table->string('provider_refund_id', 191)->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_payment_webhook_events')) {
            Schema::create('ext_chatbot_payment_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
                $table->string('provider', 100)->index();
                $table->string('event_id', 191)->nullable()->index();
                $table->string('event_key', 64)->unique();
                $table->string('event_type', 120)->nullable()->index();
                $table->string('payload_hash', 64);
                $table->boolean('signature_valid')->default(false)->index();
                $table->string('status', 40)->default('received')->index();
                $table->unsignedSmallInteger('attempt_count')->default(0);
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('received_at')->index();
                $table->timestamp('processed_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('ext_chatbot_checkout_sessions') && ! Schema::hasColumn('ext_chatbot_checkout_sessions', 'payment_intent_id')) {
            Schema::table('ext_chatbot_checkout_sessions', function (Blueprint $table): void {
                $table->unsignedBigInteger('payment_intent_id')->nullable()->index()->after('payment_attempt_key');
                $table->string('payment_status', 40)->nullable()->index()->after('payment_intent_id');
            });
        }

        if (Schema::hasTable('ext_chatbot_rental_payments') && ! Schema::hasColumn('ext_chatbot_rental_payments', 'payment_intent_id')) {
            Schema::table('ext_chatbot_rental_payments', function (Blueprint $table): void {
                $table->unsignedBigInteger('payment_intent_id')->nullable()->index()->after('provider_payment_id');
            });
        }
    }

    public function down(): void
    {
        // Financial records are preserved for auditability and safe rollback.
    }
};
