<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ext_chatbots', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_chatbots', 'is_shop')) {
                $table->boolean('is_shop')->default(false);
            }
            if (! Schema::hasColumn('ext_chatbots', 'shop_source')) {
                $table->string('shop_source')->nullable();
            }
            if (! Schema::hasColumn('ext_chatbots', 'shop_features')) {
                $table->json('shop_features')->nullable();
            }
            if (! Schema::hasColumn('ext_chatbots', 'shopify_domain')) {
                $table->string('shopify_domain')->nullable();
            }
            if (! Schema::hasColumn('ext_chatbots', 'shopify_access_token')) {
                $table->string('shopify_access_token')->nullable();
            }
            if (! Schema::hasColumn('ext_chatbots', 'woocommerce_domain')) {
                $table->string('woocommerce_domain')->nullable();
            }
            if (! Schema::hasColumn('ext_chatbots', 'woocommerce_consumer_key')) {
                $table->string('woocommerce_consumer_key')->nullable();
            }
            if (! Schema::hasColumn('ext_chatbots', 'woocommerce_consumer_secret')) {
                $table->string('woocommerce_consumer_secret')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_chatbots', function (Blueprint $table) {
            $table->dropColumn([
                'is_shop',
                'shop_source',
                'shop_features',
                'shopify_domain',
                'shopify_access_token',
                'woocommerce_domain',
                'woocommerce_consumer_key',
                'woocommerce_consumer_secret',
            ]);
        });
    }
};
