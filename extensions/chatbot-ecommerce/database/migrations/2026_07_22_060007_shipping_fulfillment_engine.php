<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_shipping_zones')) {
            Schema::create('ext_chatbot_shipping_zones', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('name', 191);
                $table->integer('priority')->default(0)->index();
                $table->boolean('active')->default(true)->index();
                $table->json('countries')->nullable();
                $table->json('regions')->nullable();
                $table->json('postcode_patterns')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_shipping_methods')) {
            Schema::create('ext_chatbot_shipping_methods', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('shipping_zone_id')->nullable()->index();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('code', 100)->index();
                $table->string('name', 191);
                $table->string('method_type', 30)->default('delivery')->index();
                $table->string('rate_type', 30)->default('flat')->index();
                $table->unsignedBigInteger('amount')->default(0);
                $table->unsignedBigInteger('base_amount')->default(0);
                $table->unsignedBigInteger('per_kg_amount')->default(0);
                $table->unsignedBigInteger('free_above')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->unsignedBigInteger('minimum_subtotal')->default(0);
                $table->unsignedBigInteger('maximum_subtotal')->nullable();
                $table->unsignedBigInteger('minimum_weight_grams')->default(0);
                $table->unsignedBigInteger('maximum_weight_grams')->nullable();
                $table->unsignedSmallInteger('estimated_days_min')->nullable();
                $table->unsignedSmallInteger('estimated_days_max')->nullable();
                $table->integer('priority')->default(0)->index();
                $table->boolean('active')->default(true)->index();
                $table->json('configuration')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['shipping_zone_id', 'chatbot_id', 'code'], 'chatbot_shipping_method_scope_code_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_shipping_quotes')) {
            Schema::create('ext_chatbot_shipping_quotes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('checkout_session_id')->nullable()->index();
                $table->unsignedBigInteger('cart_id')->index();
                $table->unsignedBigInteger('shipping_method_id')->nullable()->index();
                $table->string('quote_key', 64)->unique();
                $table->string('status', 30)->default('active')->index();
                $table->string('code', 100)->index();
                $table->string('method_type', 30)->index();
                $table->string('name', 191);
                $table->string('currency', 3);
                $table->unsignedBigInteger('amount')->default(0);
                $table->string('address_hash', 64)->nullable()->index();
                $table->unsignedInteger('cart_version')->default(0);
                $table->string('pricing_snapshot_hash', 64)->nullable()->index();
                $table->unsignedBigInteger('subtotal')->default(0);
                $table->unsignedBigInteger('weight_grams')->default(0);
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('selected_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['cart_id', 'status', 'expires_at'], 'chatbot_shipping_quote_cart_status_expiry_index');
            });
        }

        if (! Schema::hasTable('ext_chatbot_fulfillments')) {
            Schema::create('ext_chatbot_fulfillments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('checkout_session_id')->index();
                $table->unsignedBigInteger('cart_id')->index();
                $table->string('order_reference', 191)->nullable()->index();
                $table->string('status', 30)->default('pending')->index();
                $table->string('method_type', 30)->default('delivery')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->string('carrier', 120)->nullable();
                $table->string('service', 120)->nullable();
                $table->string('tracking_number', 191)->nullable()->index();
                $table->text('tracking_url')->nullable();
                $table->text('customer_note')->nullable();
                $table->text('internal_note')->nullable();
                $table->string('idempotency_key', 191)->nullable()->unique();
                $table->timestamp('processing_at')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_fulfillment_items')) {
            Schema::create('ext_chatbot_fulfillment_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('fulfillment_id')->index();
                $table->unsignedBigInteger('cart_line_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->string('sku', 191)->nullable()->index();
                $table->string('name', 255);
                $table->unsignedInteger('quantity');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('ext_chatbot_checkout_sessions')) {
            Schema::table('ext_chatbot_checkout_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('ext_chatbot_checkout_sessions', 'shipping_quote_id')) {
                    $table->unsignedBigInteger('shipping_quote_id')->nullable()->index();
                }
                if (! Schema::hasColumn('ext_chatbot_checkout_sessions', 'fulfillment_status')) {
                    $table->string('fulfillment_status', 30)->default('unfulfilled')->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Shipping quotes and fulfilment history are retained for safe rollback and auditability.
    }
};
