<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'ext_chatbot_product_categories',
            'ext_chatbot_products',
            'ext_chatbot_coupons',
            'ext_chatbot_fulfillments',
        ] as $tenantTable) {
            if (Schema::hasTable($tenantTable) && ! Schema::hasColumn($tenantTable, 'chatbot_id')) {
                Schema::table($tenantTable, function (Blueprint $table): void {
                    $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                });
            }
        }

        $singleChatbotId = null;
        if (Schema::hasTable('ext_chatbots') && DB::table('ext_chatbots')->count() === 1) {
            $singleChatbotId = (int) DB::table('ext_chatbots')->value('id');
        }
        if ($singleChatbotId !== null) {
            foreach ([
                'ext_chatbot_product_categories', 'ext_chatbot_products', 'ext_chatbot_coupons',
                'ext_chatbot_pricing_rules', 'ext_chatbot_tax_zones', 'ext_chatbot_tax_rates',
                'ext_chatbot_tax_exemptions', 'ext_chatbot_shipping_zones', 'ext_chatbot_shipping_methods',
                'ext_chatbot_inventory_locations', 'ext_chatbot_rental_accounts',
            ] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'chatbot_id')) {
                    DB::table($table)->whereNull('chatbot_id')->update(['chatbot_id' => $singleChatbotId]);
                }
            }
        }

        $this->backfillUnambiguousCatalogueTenancy();

        if (Schema::hasTable('ext_chatbot_fulfillments') && Schema::hasTable('ext_chatbot_checkout_sessions')) {
            DB::table('ext_chatbot_fulfillments')
                ->whereNull('chatbot_id')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $chatbotId = DB::table('ext_chatbot_checkout_sessions')->where('id', $row->checkout_session_id)->value('chatbot_id');
                        if ($chatbotId) {
                            DB::table('ext_chatbot_fulfillments')->where('id', $row->id)->update(['chatbot_id' => $chatbotId]);
                        }
                    }
                });
        }

        if (! Schema::hasTable('ext_chatbot_ecommerce_credentials')) {
            Schema::create('ext_chatbot_ecommerce_credentials', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('owner_user_id')->index();
                $table->string('provider', 40)->index();
                $table->longText('credentials')->nullable();
                $table->longText('configuration')->nullable();
                $table->string('credential_reference', 191)->nullable()->index();
                $table->string('status', 30)->default('active')->index();
                $table->unsignedInteger('version')->default(1);
                $table->string('fingerprint', 64)->nullable();
                $table->boolean('last_test_succeeded')->nullable();
                $table->string('last_test_error_hash', 64)->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->timestamp('rotated_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'provider'], 'ext_chatbot_ecommerce_credentials_chatbot_provider_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbots')) {
            return;
        }

        DB::table('ext_chatbots')->select([
            'id', 'user_id', 'shopify_domain', 'shopify_access_token',
            'woocommerce_domain', 'woocommerce_consumer_key', 'woocommerce_consumer_secret',
        ])->orderBy('id')->chunkById(100, function ($chatbots): void {
            foreach ($chatbots as $chatbot) {
                $this->migrateProvider(
                    (int) $chatbot->id,
                    (int) $chatbot->user_id,
                    'shopify',
                    ['access_token' => (string) ($chatbot->shopify_access_token ?? '')],
                    ['domain' => (string) ($chatbot->shopify_domain ?? '')],
                );
                $this->migrateProvider(
                    (int) $chatbot->id,
                    (int) $chatbot->user_id,
                    'woocommerce',
                    [
                        'consumer_key' => (string) ($chatbot->woocommerce_consumer_key ?? ''),
                        'consumer_secret' => (string) ($chatbot->woocommerce_consumer_secret ?? ''),
                    ],
                    ['domain' => (string) ($chatbot->woocommerce_domain ?? '')],
                );

                DB::table('ext_chatbots')->where('id', $chatbot->id)->update([
                    'shopify_access_token' => null,
                    'woocommerce_consumer_key' => null,
                    'woocommerce_consumer_secret' => null,
                ]);
            }
        });
    }

    private function backfillUnambiguousCatalogueTenancy(): void
    {
        if (Schema::hasTable('ext_chatbot_products') && Schema::hasTable('ext_chatbot_cart_lines') && Schema::hasTable('ext_chatbot_carts')) {
            DB::table('ext_chatbot_products')->whereNull('chatbot_id')->select('id')->orderBy('id')->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    $ids = DB::table('ext_chatbot_cart_lines as line')
                        ->join('ext_chatbot_carts as cart', 'cart.id', '=', 'line.cart_id')
                        ->where('line.product_id', $product->id)->whereNotNull('cart.chatbot_id')
                        ->distinct()->limit(2)->pluck('cart.chatbot_id');
                    if ($ids->count() === 1) { DB::table('ext_chatbot_products')->where('id', $product->id)->update(['chatbot_id' => (int) $ids->first()]); }
                }
            });
        }
        if (Schema::hasTable('ext_chatbot_product_categories') && Schema::hasTable('ext_chatbot_products')) {
            DB::table('ext_chatbot_product_categories')->whereNull('chatbot_id')->select('id')->orderBy('id')->chunkById(100, function ($categories): void {
                foreach ($categories as $category) {
                    $ids = DB::table('ext_chatbot_products')->where('category_id', $category->id)->whereNotNull('chatbot_id')->distinct()->limit(2)->pluck('chatbot_id');
                    if ($ids->count() === 1) { DB::table('ext_chatbot_product_categories')->where('id', $category->id)->update(['chatbot_id' => (int) $ids->first()]); }
                }
            });
        }
        if (Schema::hasTable('ext_chatbot_coupons') && Schema::hasTable('ext_chatbot_coupon_usages') && Schema::hasTable('ext_chatbot_carts')) {
            DB::table('ext_chatbot_coupons')->whereNull('chatbot_id')->select('id')->orderBy('id')->chunkById(100, function ($coupons): void {
                foreach ($coupons as $coupon) {
                    $ids = DB::table('ext_chatbot_coupon_usages as usage')
                        ->join('ext_chatbot_carts as cart', 'cart.id', '=', 'usage.cart_id')
                        ->where('usage.coupon_id', $coupon->id)->whereNotNull('cart.chatbot_id')
                        ->distinct()->limit(2)->pluck('cart.chatbot_id');
                    if ($ids->count() === 1) { DB::table('ext_chatbot_coupons')->where('id', $coupon->id)->update(['chatbot_id' => (int) $ids->first()]); }
                }
            });
        }
    }

    /** @param array<string,string> $credentials @param array<string,string> $configuration */
    private function migrateProvider(int $chatbotId, int $ownerUserId, string $provider, array $credentials, array $configuration): void
    {
        $credentials = array_filter($credentials, static fn (string $value): bool => trim($value) !== '');
        if ($credentials === []) {
            return;
        }
        $now = now();
        DB::table('ext_chatbot_ecommerce_credentials')->updateOrInsert(
            ['chatbot_id' => $chatbotId, 'provider' => $provider],
            [
                'uuid' => (string) Str::uuid(),
                'owner_user_id' => $ownerUserId,
                'credentials' => Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)),
                'configuration' => Crypt::encryptString(json_encode($configuration, JSON_THROW_ON_ERROR)),
                'status' => 'active',
                'version' => 1,
                'fingerprint' => hash('sha256', json_encode(array_keys($credentials), JSON_THROW_ON_ERROR) . ':' . implode(':', array_map(static fn ($v) => substr(hash('sha256', $v), -8), $credentials))),
                'rotated_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        // Never restore secrets to plaintext ext_chatbots columns. The encrypted table is retained.
    }
};
