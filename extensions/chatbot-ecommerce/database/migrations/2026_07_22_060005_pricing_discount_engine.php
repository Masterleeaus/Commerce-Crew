<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ext_chatbot_coupons')) {
            Schema::table('ext_chatbot_coupons', function (Blueprint $table): void {
                $columns = [
                    'max_discount_amount' => fn () => $table->unsignedBigInteger('max_discount_amount')->nullable(),
                    'usage_limit' => fn () => $table->unsignedInteger('usage_limit')->nullable(),
                    'usage_limit_per_customer' => fn () => $table->unsignedInteger('usage_limit_per_customer')->nullable(),
                    'first_order_only' => fn () => $table->boolean('first_order_only')->default(false),
                    'stackable' => fn () => $table->boolean('stackable')->default(true),
                    'applies_to' => fn () => $table->string('applies_to', 30)->default('cart')->index(),
                    'target_ids' => fn () => $table->json('target_ids')->nullable(),
                    'channels' => fn () => $table->json('channels')->nullable(),
                    'currency' => fn () => $table->string('currency', 3)->nullable(),
                ];

                foreach ($columns as $name => $add) {
                    if (! Schema::hasColumn('ext_chatbot_coupons', $name)) {
                        $add();
                    }
                }
            });
        }

        if (Schema::hasTable('ext_chatbot_carts')) {
            Schema::table('ext_chatbot_carts', function (Blueprint $table): void {
                $columns = [
                    'list_subtotal' => fn () => $table->unsignedBigInteger('list_subtotal')->default(0),
                    'item_discount_total' => fn () => $table->unsignedBigInteger('item_discount_total')->default(0),
                    'shipping_subtotal' => fn () => $table->unsignedBigInteger('shipping_subtotal')->default(0),
                    'shipping_discount_total' => fn () => $table->unsignedBigInteger('shipping_discount_total')->default(0),
                    'pricing_snapshot_uuid' => fn () => $table->uuid('pricing_snapshot_uuid')->nullable()->index(),
                    'pricing_snapshot_hash' => fn () => $table->string('pricing_snapshot_hash', 64)->nullable()->index(),
                    'calculation_version' => fn () => $table->string('calculation_version', 20)->nullable(),
                ];
                foreach ($columns as $name => $add) {
                    if (! Schema::hasColumn('ext_chatbot_carts', $name)) {
                        $add();
                    }
                }
            });
        }

        if (Schema::hasTable('ext_chatbot_cart_lines')) {
            Schema::table('ext_chatbot_cart_lines', function (Blueprint $table): void {
                $columns = [
                    'list_unit_price' => fn () => $table->unsignedBigInteger('list_unit_price')->nullable(),
                    'gross_total' => fn () => $table->unsignedBigInteger('gross_total')->nullable(),
                    'net_before_tax' => fn () => $table->unsignedBigInteger('net_before_tax')->nullable(),
                    'tax_rate_bps' => fn () => $table->unsignedInteger('tax_rate_bps')->default(0),
                    'discounts' => fn () => $table->json('discounts')->nullable(),
                ];
                foreach ($columns as $name => $add) {
                    if (! Schema::hasColumn('ext_chatbot_cart_lines', $name)) {
                        $add();
                    }
                }
            });
        }

        if (! Schema::hasTable('ext_chatbot_pricing_rules')) {
            Schema::create('ext_chatbot_pricing_rules', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('name');
                $table->string('rule_type', 40)->index();
                $table->string('scope', 20)->default('line')->index();
                $table->string('target_type', 20)->default('all')->index();
                $table->unsignedBigInteger('target_id')->nullable()->index();
                $table->integer('priority')->default(0)->index();
                $table->boolean('stackable')->default(false);
                $table->boolean('active')->default(true)->index();
                $table->unsignedBigInteger('value')->default(0);
                $table->string('currency', 3)->nullable();
                $table->unsignedInteger('min_quantity')->default(1);
                $table->unsignedBigInteger('min_subtotal')->default(0);
                $table->string('channel', 40)->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->timestamp('starts_at')->nullable()->index();
                $table->timestamp('ends_at')->nullable()->index();
                $table->json('conditions')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_pricing_snapshots')) {
            Schema::create('ext_chatbot_pricing_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('cart_id')->index();
                $table->unsignedInteger('cart_version');
                $table->string('calculation_version', 20)->default('1');
                $table->string('currency', 3);
                $table->unsignedBigInteger('subtotal')->default(0);
                $table->unsignedBigInteger('discount_total')->default(0);
                $table->unsignedBigInteger('shipping_total')->default(0);
                $table->unsignedBigInteger('tax_total')->default(0);
                $table->unsignedBigInteger('total')->default(0);
                $table->string('snapshot_hash', 64)->index();
                $table->json('calculation');
                $table->timestamp('created_at')->nullable();
                $table->unique(['cart_id', 'cart_version'], 'chatbot_pricing_snapshot_cart_version_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_coupon_usages')) {
            Schema::create('ext_chatbot_coupon_usages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('coupon_id')->index();
                $table->unsignedBigInteger('cart_id')->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('status', 20)->default('reserved')->index();
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('redeemed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['coupon_id', 'cart_id'], 'chatbot_coupon_usage_cart_unique');
            });
        }
    }

    public function down(): void
    {
        // Pricing history and coupon usage are retained for safe rollback and auditability.
    }
};
