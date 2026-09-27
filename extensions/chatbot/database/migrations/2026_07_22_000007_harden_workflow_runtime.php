<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_workflow_runs')) {
            return;
        }

        Schema::table('ext_chatbot_workflow_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'conversation_id')) {
                $table->unsignedBigInteger('conversation_id')->nullable()->index()->after('uuid');
            }
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->index()->after('workflow');
            }
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'attempt')) {
                $table->unsignedInteger('attempt')->default(1)->after('status');
            }
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'max_attempts')) {
                $table->unsignedInteger('max_attempts')->default(3)->after('attempt');
            }
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'available_at')) {
                $table->timestamp('available_at')->nullable()->index()->after('output');
            }
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->index()->after('available_at');
            }
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'lock_token')) {
                $table->uuid('lock_token')->nullable()->index()->after('locked_at');
            }
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'error')) {
                $table->text('error')->nullable()->after('lock_token');
            }
            if (! Schema::hasColumn('ext_chatbot_workflow_runs', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('finished_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ext_chatbot_workflow_runs')) {
            return;
        }

        $columns = array_values(array_filter([
            'conversation_id', 'idempotency_key', 'attempt', 'max_attempts', 'available_at',
            'locked_at', 'lock_token', 'error', 'cancelled_at',
        ], fn (string $column): bool => Schema::hasColumn('ext_chatbot_workflow_runs', $column)));

        if ($columns !== []) {
            Schema::table('ext_chatbot_workflow_runs', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
