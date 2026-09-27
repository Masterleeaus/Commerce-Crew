<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_commerce_contexts')) {
            Schema::create('ext_chatbot_commerce_contexts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->string('session_id', 191)->index();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->unsignedBigInteger('cart_id')->nullable()->index();
                $table->json('current_product_ids')->nullable();
                $table->text('last_query')->nullable();
                $table->json('last_filters')->nullable();
                $table->json('short_term_preferences')->nullable();
                $table->json('pending_actions')->nullable();
                $table->unsignedBigInteger('selected_product_id')->nullable()->index();
                $table->unsignedBigInteger('selected_variant_id')->nullable()->index();
                $table->text('compressed_summary')->nullable();
                $table->unsignedInteger('context_version')->default(1);
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('last_accessed_at')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'session_id'], 'commerce_context_chatbot_session_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_spend_limits')) {
            Schema::create('ext_chatbot_commerce_spend_limits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('session_id', 191)->nullable()->index();
                $table->char('currency', 3)->default('USD');
                $table->integer('max_order_value')->nullable();
                $table->integer('daily_spend_limit')->nullable();
                $table->integer('spent_today')->default(0);
                $table->date('period_date')->nullable()->index();
                $table->boolean('enabled')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_error_lexicon')) {
            Schema::create('ext_chatbot_commerce_error_lexicon', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('provider', 80)->index();
                $table->string('error_code', 120)->index();
                $table->string('title', 191)->nullable();
                $table->text('message');
                $table->text('remediation')->nullable();
                $table->string('severity', 30)->default('warning');
                $table->boolean('retryable')->default(false);
                $table->boolean('active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'provider', 'error_code'], 'commerce_error_lexicon_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_orders')) {
            Schema::create('ext_chatbot_commerce_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('order_number', 80)->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('cart_id')->nullable()->index();
                $table->unsignedBigInteger('checkout_session_id')->nullable()->unique();
                $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->string('session_id', 191)->nullable()->index();
                $table->string('source', 40)->default('internal')->index();
                $table->string('status', 40)->default('pending')->index();
                $table->string('fulfillment_status', 40)->default('unfulfilled')->index();
                $table->char('currency', 3);
                $table->integer('subtotal')->default(0);
                $table->integer('discount_total')->default(0);
                $table->integer('tax_total')->default(0);
                $table->integer('shipping_total')->default(0);
                $table->integer('total')->default(0);
                $table->integer('refunded_total')->default(0);
                $table->json('customer_snapshot')->nullable();
                $table->json('billing_address')->nullable();
                $table->json('shipping_address')->nullable();
                $table->json('pricing_snapshot')->nullable();
                $table->json('tax_breakdown')->nullable();
                $table->json('payment_snapshot')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('placed_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_order_items')) {
            Schema::create('ext_chatbot_commerce_order_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->string('sku', 191)->nullable()->index();
                $table->string('name', 255);
                $table->string('variant_name', 255)->nullable();
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('fulfilled_quantity')->default(0);
                $table->unsignedInteger('returned_quantity')->default(0);
                $table->integer('list_unit_price')->default(0);
                $table->integer('unit_price')->default(0);
                $table->integer('discount_total')->default(0);
                $table->integer('tax_total')->default(0);
                $table->integer('line_total')->default(0);
                $table->json('product_snapshot')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_order_events')) {
            Schema::create('ext_chatbot_commerce_order_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('event_type', 80)->index();
                $table->string('actor_type', 40)->default('system');
                $table->string('actor_id', 191)->nullable();
                $table->json('before_state')->nullable();
                $table->json('after_state')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_returns')) {
            Schema::create('ext_chatbot_commerce_returns', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('rma_number', 80)->unique();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('status', 40)->default('requested')->index();
                $table->string('reason_code', 80)->nullable()->index();
                $table->text('reason')->nullable();
                $table->string('requested_resolution', 40)->default('refund');
                $table->integer('requested_amount')->default(0);
                $table->integer('refunded_amount')->default(0);
                $table->char('currency', 3);
                $table->string('return_shipping_status', 40)->nullable();
                $table->string('tracking_number', 191)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_return_items')) {
            Schema::create('ext_chatbot_commerce_return_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('return_id')->index();
                $table->unsignedBigInteger('order_item_id')->index();
                $table->unsignedInteger('quantity');
                $table->string('reason_code', 80)->nullable();
                $table->string('condition', 80)->nullable();
                $table->string('resolution', 40)->nullable();
                $table->integer('amount')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_action_journal')) {
            Schema::create('ext_chatbot_commerce_action_journal', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->string('action_type', 120)->index();
                $table->string('subject_type', 191)->index();
                $table->string('subject_id', 191)->index();
                $table->string('status', 40)->default('prepared')->index();
                $table->string('actor_type', 40)->default('ai');
                $table->string('actor_id', 191)->nullable();
                $table->string('idempotency_key', 191)->nullable()->unique();
                $table->json('before_state')->nullable();
                $table->json('after_state')->nullable();
                $table->json('rollback_state')->nullable();
                $table->json('approval_snapshot')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Commerce contexts may be deleted separately. Orders, returns, spend controls,
        // audit journals, and error mappings are retained by default for operational safety.
    }
};
