<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ext_chatbot_products', function (Blueprint $table) {
            foreach ([
                'short_description' => fn () => $table->text('short_description')->nullable(),
                'brand' => fn () => $table->string('brand')->nullable()->index(),
                'vendor' => fn () => $table->string('vendor')->nullable(),
                'product_type' => fn () => $table->string('product_type')->default('physical')->index(),
                'images' => fn () => $table->json('images')->nullable(),
                'tags' => fn () => $table->json('tags')->nullable(),
                'search_keywords' => fn () => $table->text('search_keywords')->nullable(),
                'compare_at_price' => fn () => $table->unsignedBigInteger('compare_at_price')->nullable(),
                'cost_price' => fn () => $table->unsignedBigInteger('cost_price')->nullable(),
                'tax_class' => fn () => $table->string('tax_class')->nullable(),
                'published_at' => fn () => $table->timestamp('published_at')->nullable(),
            ] as $column => $add) if (!Schema::hasColumn('ext_chatbot_products', $column)) $add();
        });
        Schema::table('ext_chatbot_product_variants', function (Blueprint $table) {
            foreach ([
                'barcode' => fn () => $table->string('barcode')->nullable()->index(),
                'image' => fn () => $table->string('image')->nullable(),
                'weight' => fn () => $table->decimal('weight', 12, 3)->nullable(),
                'dimensions' => fn () => $table->json('dimensions')->nullable(),
                'active' => fn () => $table->boolean('active')->default(true)->index(),
                'allow_backorder' => fn () => $table->boolean('allow_backorder')->default(false),
            ] as $column => $add) if (!Schema::hasColumn('ext_chatbot_product_variants', $column)) $add();
        });
        Schema::table('ext_chatbot_inventory', function (Blueprint $table) {
            foreach ([
                'committed' => fn () => $table->integer('committed')->default(0),
                'incoming' => fn () => $table->integer('incoming')->default(0),
                'damaged' => fn () => $table->integer('damaged')->default(0),
                'safety_stock' => fn () => $table->integer('safety_stock')->default(0),
                'low_stock_threshold' => fn () => $table->integer('low_stock_threshold')->default(0),
                'version' => fn () => $table->unsignedInteger('version')->default(0),
                'metadata' => fn () => $table->json('metadata')->nullable(),
            ] as $column => $add) if (!Schema::hasColumn('ext_chatbot_inventory', $column)) $add();
        });
        Schema::table('ext_chatbot_carts', function (Blueprint $table) {
            foreach ([
                'status' => fn () => $table->string('status')->default('active')->index(),
                'currency' => fn () => $table->string('currency', 3)->default('USD'),
                'lines' => fn () => $table->json('lines')->nullable(),
                'coupon_code' => fn () => $table->string('coupon_code')->nullable(),
                'subtotal' => fn () => $table->unsignedBigInteger('subtotal')->default(0),
                'discount_total' => fn () => $table->unsignedBigInteger('discount_total')->default(0),
                'tax_total' => fn () => $table->unsignedBigInteger('tax_total')->default(0),
                'shipping_total' => fn () => $table->unsignedBigInteger('shipping_total')->default(0),
                'total' => fn () => $table->unsignedBigInteger('total')->default(0),
                'price_snapshot' => fn () => $table->json('price_snapshot')->nullable(),
                'expires_at' => fn () => $table->timestamp('expires_at')->nullable()->index(),
                'metadata' => fn () => $table->json('metadata')->nullable(),
            ] as $column => $add) if (!Schema::hasColumn('ext_chatbot_carts', $column)) $add();
        });
    }

    public function down(): void {}
};
