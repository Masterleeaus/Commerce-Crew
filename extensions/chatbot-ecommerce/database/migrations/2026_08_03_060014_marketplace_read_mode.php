<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_marketplace_connections')) {
            Schema::create('ext_chatbot_marketplace_connections', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('owner_user_id')->index(); $table->string('provider', 40)->index();
                $table->string('name', 191); $table->string('marketplace', 40)->nullable()->index();
                $table->string('region', 20)->nullable(); $table->string('external_account_id', 191)->nullable()->index();
                $table->string('credential_reference', 191); $table->json('configuration')->nullable();
                $table->json('capabilities')->nullable(); $table->boolean('active')->default(true)->index();
                $table->timestamp('last_verified_at')->nullable(); $table->timestamp('last_read_at')->nullable();
                $table->text('last_error')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
                $table->unique(['chatbot_id','provider','external_account_id'], 'marketplace_connection_account_unique');
            });
        }
        if (! Schema::hasTable('ext_chatbot_marketplace_searches')) {
            Schema::create('ext_chatbot_marketplace_searches', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('chatbot_id')->index();
                $table->string('session_id', 191)->index(); $table->string('cache_key', 64)->index();
                $table->text('query'); $table->json('filters')->nullable(); $table->json('providers')->nullable();
                $table->string('status', 30)->default('queued')->index(); $table->unsignedInteger('provider_count')->default(0);
                $table->unsignedInteger('completed_provider_count')->default(0); $table->unsignedInteger('result_count')->default(0);
                $table->boolean('cache_hit')->default(false); $table->json('errors')->nullable();
                $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->index(); $table->json('metadata')->nullable(); $table->timestamps();
            });
        }
        if (! Schema::hasTable('ext_chatbot_marketplace_search_results')) {
            Schema::create('ext_chatbot_marketplace_search_results', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('search_id')->index();
                $table->unsignedBigInteger('connection_id')->nullable()->index(); $table->string('provider', 40)->index();
                $table->string('external_listing_id', 191); $table->string('title'); $table->text('description')->nullable();
                $table->integer('price_amount')->default(0); $table->char('currency', 3)->default('USD');
                $table->text('image_url')->nullable(); $table->text('product_url')->nullable();
                $table->string('availability', 50)->default('unknown')->index(); $table->string('seller_name')->nullable();
                $table->text('shipping_summary')->nullable(); $table->text('bnpl_summary')->nullable();
                $table->decimal('relevance_score', 6, 5)->default(0); $table->string('source_hash', 64)->index();
                $table->json('attributes')->nullable(); $table->unsignedInteger('position')->default(0); $table->timestamps();
                $table->unique(['search_id','provider','external_listing_id'], 'marketplace_search_result_unique');
            });
        }
        if (! Schema::hasTable('ext_chatbot_marketplace_listing_snapshots')) {
            Schema::create('ext_chatbot_marketplace_listing_snapshots', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->index(); $table->string('provider', 40)->index();
                $table->string('external_listing_id', 191); $table->string('source_hash', 64)->index();
                $table->json('snapshot'); $table->timestamp('first_seen_at'); $table->timestamp('last_seen_at')->index(); $table->timestamps();
                $table->unique(['connection_id','external_listing_id'], 'marketplace_listing_snapshot_unique');
            });
        }
        if (! Schema::hasTable('ext_chatbot_marketplace_order_snapshots')) {
            Schema::create('ext_chatbot_marketplace_order_snapshots', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->index(); $table->string('provider', 40)->index();
                $table->string('external_order_id', 191); $table->string('status', 60)->index();
                $table->char('currency', 3); $table->integer('total_amount')->default(0);
                $table->string('buyer_display_name')->nullable(); $table->string('fulfillment_status', 60)->nullable()->index();
                $table->string('source_hash', 64)->index(); $table->json('snapshot');
                $table->timestamp('placed_at')->nullable()->index(); $table->timestamp('provider_updated_at')->nullable();
                $table->timestamp('first_imported_at'); $table->timestamp('last_imported_at')->index(); $table->timestamps();
                $table->unique(['connection_id', 'external_order_id'], 'marketplace_order_snapshot_unique');
            });
        }
        if (! Schema::hasTable('ext_chatbot_marketplace_order_line_snapshots')) {
            Schema::create('ext_chatbot_marketplace_order_line_snapshots', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('order_snapshot_id')->index();
                $table->string('external_line_id', 191); $table->string('sku')->nullable()->index(); $table->string('title');
                $table->unsignedInteger('quantity')->default(1); $table->integer('unit_amount')->default(0); $table->integer('tax_amount')->default(0);
                $table->json('snapshot')->nullable(); $table->timestamps();
                $table->unique(['order_snapshot_id','external_line_id'], 'marketplace_order_line_snapshot_unique');
            });
        }
        if (! Schema::hasTable('ext_chatbot_marketplace_sync_cursors')) {
            Schema::create('ext_chatbot_marketplace_sync_cursors', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('connection_id')->index();
                $table->string('scope', 40)->index(); $table->json('cursor')->nullable(); $table->timestamp('last_synced_at')->nullable();
                $table->timestamps(); $table->unique(['connection_id','scope'], 'marketplace_sync_cursor_unique');
            });
        }
        if (! Schema::hasTable('ext_chatbot_marketplace_sync_runs')) {
            Schema::create('ext_chatbot_marketplace_sync_runs', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('connection_id')->index(); $table->string('scope', 40)->index();
                $table->string('status', 30)->default('queued')->index(); $table->unsignedInteger('records_read')->default(0);
                $table->unsignedInteger('records_created')->default(0); $table->unsignedInteger('records_updated')->default(0);
                $table->text('failure_reason')->nullable(); $table->json('cursor_before')->nullable(); $table->json('cursor_after')->nullable();
                $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Marketplace connections, searches, and imported order snapshots are preserved by default.
    }
};
