<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_checkout_sessions')) {
            Schema::create('ext_chatbot_checkout_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('cart_id')->index();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->string('session_id', 191)->index();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('status', 30)->default('draft')->index();
                $table->string('active_key', 64)->nullable()->unique();
                $table->string('email')->nullable()->index();
                $table->string('phone', 80)->nullable()->index();
                $table->json('billing_address')->nullable();
                $table->json('shipping_address')->nullable();
                $table->string('delivery_method', 30)->nullable()->index();
                $table->json('delivery_option')->nullable();
                $table->text('customer_note')->nullable();
                $table->json('consent')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->unsignedInteger('cart_version')->default(0);
                $table->uuid('pricing_snapshot_uuid')->nullable()->index();
                $table->string('pricing_snapshot_hash', 64)->nullable()->index();
                $table->json('pricing_snapshot')->nullable();
                $table->json('inventory_snapshot')->nullable();
                $table->unsignedBigInteger('subtotal')->default(0);
                $table->unsignedBigInteger('discount_total')->default(0);
                $table->unsignedBigInteger('tax_total')->default(0);
                $table->unsignedBigInteger('shipping_total')->default(0);
                $table->unsignedBigInteger('total')->default(0);
                $table->string('approval_token_hash', 64)->nullable()->index();
                $table->string('payment_attempt_key', 191)->nullable()->unique();
                $table->string('completion_key', 191)->nullable()->unique();
                $table->string('order_reference', 191)->nullable()->index();
                $table->string('failure_code', 80)->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('approval_expires_at')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('payment_pending_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['chatbot_id', 'session_id', 'status'], 'chatbot_checkout_session_status_index');
            });
        }

        if (! Schema::hasTable('ext_chatbot_checkout_operations')) {
            Schema::create('ext_chatbot_checkout_operations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('checkout_session_id')->index();
                $table->string('idempotency_key', 191);
                $table->string('request_hash', 64);
                $table->string('operation', 80)->index();
                $table->json('response')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['checkout_session_id', 'idempotency_key'], 'chatbot_checkout_operation_idempotency_unique');
            });
        }
    }

    public function down(): void
    {
        // Checkout history is preserved for auditability and safe rollback.
    }
};
