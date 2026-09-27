<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_inventory_locations')) {
            Schema::create('ext_chatbot_inventory_locations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('code', 100);
                $table->string('name');
                $table->boolean('active')->default(true)->index();
                $table->unsignedInteger('priority')->default(100)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'code'], 'ext_chatbot_inventory_locations_chatbot_code_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_inventory_location_stock')) {
            Schema::create('ext_chatbot_inventory_location_stock', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('variant_id')->index();
                $table->unsignedBigInteger('location_id')->index();
                $table->integer('quantity')->default(0);
                $table->integer('reserved')->default(0);
                $table->integer('committed')->default(0);
                $table->integer('incoming')->default(0);
                $table->integer('damaged')->default(0);
                $table->integer('safety_stock')->default(0);
                $table->integer('low_stock_threshold')->default(0);
                $table->unsignedInteger('version')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['variant_id', 'location_id'], 'ext_chatbot_inventory_location_stock_variant_location_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_inventory_reservations')) {
            Schema::create('ext_chatbot_inventory_reservations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('cart_id')->index();
                $table->unsignedBigInteger('variant_id')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedInteger('quantity');
                $table->string('status', 32)->default('active')->index();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamp('expires_at')->index();
                $table->timestamp('committed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->string('release_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['variant_id', 'location_id', 'status'], 'ext_chatbot_inventory_reservations_stock_status_index');
            });
        }

        if (! Schema::hasTable('ext_chatbot_inventory_adjustments')) {
            Schema::create('ext_chatbot_inventory_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('variant_id')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->integer('delta');
                $table->string('reason', 100);
                $table->integer('balance_after');
                $table->unsignedInteger('version');
                $table->unsignedBigInteger('actor_id')->nullable()->index();
                $table->string('idempotency_key', 191)->nullable()->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Operational inventory history is intentionally preserved during extension rollback.
    }
};
