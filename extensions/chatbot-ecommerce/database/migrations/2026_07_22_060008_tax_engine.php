<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_tax_zones')) {
            Schema::create('ext_chatbot_tax_zones', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('name', 191);
                $table->integer('priority')->default(0)->index();
                $table->boolean('active')->default(true)->index();
                $table->boolean('prices_include_tax')->nullable();
                $table->json('countries')->nullable();
                $table->json('regions')->nullable();
                $table->json('postcode_patterns')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_tax_rates')) {
            Schema::create('ext_chatbot_tax_rates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tax_zone_id')->nullable()->index();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('code', 100)->index();
                $table->string('name', 191);
                $table->string('tax_class', 100)->default('default')->index();
                $table->unsignedInteger('rate_bps')->default(0);
                $table->boolean('compound')->default(false);
                $table->boolean('applies_to_products')->default(true)->index();
                $table->boolean('applies_to_shipping')->default(false)->index();
                $table->integer('priority')->default(0)->index();
                $table->boolean('active')->default(true)->index();
                $table->timestamp('starts_at')->nullable()->index();
                $table->timestamp('ends_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['tax_zone_id', 'tax_class', 'active'], 'chatbot_tax_rate_zone_class_active_index');
            });
        }

        if (! Schema::hasTable('ext_chatbot_tax_exemptions')) {
            Schema::create('ext_chatbot_tax_exemptions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('exemption_key', 191)->unique();
                $table->string('tax_class', 100)->nullable()->index();
                $table->text('reason')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->timestamp('starts_at')->nullable()->index();
                $table->timestamp('ends_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('ext_chatbot_carts')) {
            Schema::table('ext_chatbot_carts', function (Blueprint $table): void {
                if (! Schema::hasColumn('ext_chatbot_carts', 'tax_zone_id')) {
                    $table->unsignedBigInteger('tax_zone_id')->nullable()->index();
                }
                if (! Schema::hasColumn('ext_chatbot_carts', 'tax_context_hash')) {
                    $table->string('tax_context_hash', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('ext_chatbot_carts', 'tax_breakdown')) {
                    $table->json('tax_breakdown')->nullable();
                }
            });
        }

        if (Schema::hasTable('ext_chatbot_checkout_sessions')) {
            Schema::table('ext_chatbot_checkout_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('ext_chatbot_checkout_sessions', 'tax_zone_id')) {
                    $table->unsignedBigInteger('tax_zone_id')->nullable()->index();
                }
                if (! Schema::hasColumn('ext_chatbot_checkout_sessions', 'tax_context_hash')) {
                    $table->string('tax_context_hash', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('ext_chatbot_checkout_sessions', 'tax_breakdown')) {
                    $table->json('tax_breakdown')->nullable();
                }
                if (! Schema::hasColumn('ext_chatbot_checkout_sessions', 'tax_exemption_key')) {
                    $table->string('tax_exemption_key', 191)->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Tax configuration and calculation history are retained for safe rollback and auditability.
    }
};
