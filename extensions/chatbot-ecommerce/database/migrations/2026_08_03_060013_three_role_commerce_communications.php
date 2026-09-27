<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_commerce_communication_threads')) {
            Schema::create('ext_chatbot_commerce_communication_threads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->unsignedBigInteger('owner_user_id')->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->string('session_id', 191)->nullable()->index();
                $table->string('role', 60)->default('customer_communications')->index();
                $table->string('channel', 60)->default('web')->index();
                $table->string('external_thread_id', 191)->nullable()->index();
                $table->string('subject', 255)->nullable();
                $table->string('status', 40)->default('open')->index();
                $table->string('priority', 30)->default('normal')->index();
                $table->string('intent', 100)->nullable()->index();
                $table->string('sentiment', 40)->nullable()->index();
                $table->decimal('confidence', 5, 4)->nullable();
                $table->boolean('identity_verified')->default(false)->index();
                $table->string('assigned_to_type', 40)->nullable();
                $table->string('assigned_to_id', 191)->nullable()->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'channel', 'external_thread_id'], 'commerce_comm_thread_external_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_communication_messages')) {
            Schema::create('ext_chatbot_commerce_communication_messages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('thread_id')->index();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->string('channel', 60)->default('web')->index();
                $table->string('external_message_id', 191)->nullable();
                $table->string('direction', 20)->index();
                $table->string('actor_type', 40)->default('customer')->index();
                $table->string('actor_id', 191)->nullable()->index();
                $table->string('role', 60)->default('customer_communications')->index();
                $table->string('message_type', 40)->default('text');
                $table->text('body')->nullable();
                $table->text('safe_summary')->nullable();
                $table->string('intent', 100)->nullable()->index();
                $table->decimal('confidence', 5, 4)->nullable();
                $table->string('provider', 80)->nullable()->index();
                $table->string('provider_message_id', 191)->nullable()->index();
                $table->unsignedBigInteger('reply_to_message_id')->nullable()->index();
                $table->json('attachments')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'channel', 'external_message_id'], 'commerce_comm_message_external_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_communication_policies')) {
            Schema::create('ext_chatbot_commerce_communication_policies', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->unique();
                $table->boolean('auto_reply_enabled')->default(false);
                $table->decimal('auto_reply_confidence', 5, 4)->default(0.9000);
                $table->integer('automatic_refund_limit')->default(0);
                $table->integer('automatic_discount_limit')->default(0);
                $table->integer('automatic_store_credit_limit')->default(0);
                $table->integer('automatic_replacement_limit')->default(0);
                $table->integer('automatic_cancellation_limit')->default(0);
                $table->char('currency', 3)->default('USD');
                $table->boolean('require_identity_for_order_data')->default(true);
                $table->boolean('human_handoff')->default(true);
                $table->unsignedInteger('return_window_days')->default(30);
                $table->json('allowed_auto_actions')->nullable();
                $table->json('escalation_triggers')->nullable();
                $table->json('enabled_channels')->nullable();
                $table->json('specialist_product_ids')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_communication_actions')) {
            Schema::create('ext_chatbot_commerce_communication_actions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('thread_id')->index();
                $table->unsignedBigInteger('message_id')->nullable()->index();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->string('action_type', 100)->index();
                $table->string('authority_level', 40)->index();
                $table->string('status', 40)->default('prepared')->index();
                $table->integer('amount')->nullable();
                $table->char('currency', 3)->nullable();
                $table->string('idempotency_key', 191)->nullable()->index();
                $table->string('approval_token_hash', 64)->nullable()->index();
                $table->string('approved_by_type', 40)->nullable();
                $table->string('approved_by_id', 191)->nullable();
                $table->string('executed_by_type', 40)->nullable();
                $table->string('executed_by_id', 191)->nullable();
                $table->json('payload')->nullable();
                $table->json('result')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'idempotency_key'], 'commerce_comm_action_idempotency_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_commerce_escalations')) {
            Schema::create('ext_chatbot_commerce_escalations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('thread_id')->index();
                $table->unsignedBigInteger('message_id')->nullable()->index();
                $table->unsignedBigInteger('chatbot_id')->index();
                $table->string('reason', 100)->index();
                $table->string('severity', 30)->default('normal')->index();
                $table->string('status', 40)->default('open')->index();
                $table->text('summary');
                $table->text('recommended_action')->nullable();
                $table->json('context_snapshot')->nullable();
                $table->string('assigned_to_type', 40)->nullable();
                $table->string('assigned_to_id', 191)->nullable()->index();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Communication, approval, and escalation records are preserved by default.
    }
};
