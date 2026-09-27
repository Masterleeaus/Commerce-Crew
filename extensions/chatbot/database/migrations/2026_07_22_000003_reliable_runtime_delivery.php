<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_chatbot_histories')) {
            Schema::table('ext_chatbot_histories', function (Blueprint $table): void {
                if (! Schema::hasColumn('ext_chatbot_histories', 'idempotency_key')) {
                    $table->string('idempotency_key', 191)->nullable()->index();
                }
                if (! Schema::hasColumn('ext_chatbot_histories', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable();
                }
                if (! Schema::hasColumn('ext_chatbot_histories', 'failed_at')) {
                    $table->timestamp('failed_at')->nullable();
                }
                $table->unique(['conversation_id', 'idempotency_key'], 'chatbot_history_idempotency_unique');
            });
        }

        if (Schema::hasTable('ext_chatbot_event_outbox')) {
            Schema::table('ext_chatbot_event_outbox', function (Blueprint $table): void {
                if (! Schema::hasColumn('ext_chatbot_event_outbox', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable()->index();
                }
                if (! Schema::hasColumn('ext_chatbot_event_outbox', 'locked_by')) {
                    $table->string('locked_by', 191)->nullable()->index();
                }
                if (! Schema::hasColumn('ext_chatbot_event_outbox', 'failed_at')) {
                    $table->timestamp('failed_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Additive runtime columns are retained for safe rollback and reinstall.
    }
};
