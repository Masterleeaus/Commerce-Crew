<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ext_chatbot_carts')) {
            Schema::table('ext_chatbot_carts', function (Blueprint $table): void {
                $columns = [
                    'uuid' => fn () => $table->uuid('uuid')->nullable()->unique(),
                    'active_key' => fn () => $table->string('active_key', 64)->nullable()->unique(),
                    'conversation_id' => fn () => $table->unsignedBigInteger('conversation_id')->nullable()->index(),
                    'channel' => fn () => $table->string('channel', 40)->nullable()->index(),
                    'customer_identity_id' => fn () => $table->unsignedBigInteger('customer_identity_id')->nullable()->index(),
                    'merged_into_cart_id' => fn () => $table->unsignedBigInteger('merged_into_cart_id')->nullable()->index(),
                    'recovery_token_hash' => fn () => $table->string('recovery_token_hash', 64)->nullable()->unique(),
                    'line_count' => fn () => $table->unsignedInteger('line_count')->default(0),
                    'version' => fn () => $table->unsignedInteger('version')->default(0),
                    'last_activity_at' => fn () => $table->timestamp('last_activity_at')->nullable()->index(),
                    'abandoned_at' => fn () => $table->timestamp('abandoned_at')->nullable(),
                    'recovered_at' => fn () => $table->timestamp('recovered_at')->nullable(),
                    'converted_at' => fn () => $table->timestamp('converted_at')->nullable(),
                ];

                foreach ($columns as $name => $add) {
                    if (! Schema::hasColumn('ext_chatbot_carts', $name)) {
                        $add();
                    }
                }
            });
        }

        if (! Schema::hasTable('ext_chatbot_cart_lines')) {
            Schema::create('ext_chatbot_cart_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('cart_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('variant_id')->index();
                $table->string('line_key', 64);
                $table->string('sku')->nullable();
                $table->string('name');
                $table->string('variant_name')->nullable();
                $table->unsignedInteger('quantity');
                $table->unsignedBigInteger('unit_price');
                $table->unsignedBigInteger('discount_total')->default(0);
                $table->unsignedBigInteger('tax_total')->default(0);
                $table->unsignedBigInteger('line_total');
                $table->string('currency', 3);
                $table->json('customisation')->nullable();
                $table->json('price_snapshot')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['cart_id', 'line_key'], 'chatbot_cart_lines_cart_key_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_cart_operations')) {
            Schema::create('ext_chatbot_cart_operations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('cart_id')->index();
                $table->string('idempotency_key', 191);
                $table->string('request_hash', 64);
                $table->string('operation', 80)->index();
                $table->json('response')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['cart_id', 'idempotency_key'], 'chatbot_cart_operations_idempotency_unique');
            });
        }
    }

    public function down(): void
    {
        // Native cart and historical operation data are preserved for safe rollback.
    }
};
