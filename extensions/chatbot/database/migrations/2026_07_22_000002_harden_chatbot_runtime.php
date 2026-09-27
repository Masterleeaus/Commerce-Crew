<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_delivery_receipts')) {
            Schema::create('ext_chatbot_delivery_receipts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('history_id')->index();
                $table->string('provider', 64)->nullable()->index();
                $table->string('provider_message_id')->nullable()->index();
                $table->string('status', 32)->index();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->nullable()->index();
                $table->timestamps();
                $table->index(['history_id', 'status'], 'chatbot_receipt_history_status');
            });
        }

        if (! Schema::hasTable('ext_chatbot_drafts')) {
            Schema::create('ext_chatbot_drafts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->longText('content')->nullable();
                $table->json('attachments')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['conversation_id', 'user_id'], 'chatbot_draft_owner_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_presence')) {
            Schema::create('ext_chatbot_presence', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id')->index();
                $table->string('participant_key', 191);
                $table->string('state', 32)->default('online')->index();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['conversation_id', 'participant_key'], 'chatbot_presence_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_event_outbox')) {
            Schema::create('ext_chatbot_event_outbox', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_uuid')->unique();
                $table->string('event_type', 191)->index();
                $table->string('aggregate_type', 191)->nullable();
                $table->string('aggregate_id', 191)->nullable();
                $table->json('payload');
                $table->string('status', 32)->default('pending')->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('available_at')->nullable()->index();
                $table->timestamp('published_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->index(['aggregate_type', 'aggregate_id'], 'chatbot_outbox_aggregate');
            });
        }
    }

    public function down(): void
    {
        // Runtime data is intentionally retained for uninstall/rollback safety.
    }
};
