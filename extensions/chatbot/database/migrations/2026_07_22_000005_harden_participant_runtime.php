<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_participants')) {
            return;
        }

        Schema::table('ext_chatbot_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('ext_chatbot_participants', 'last_read_history_id')) {
                $table->unsignedBigInteger('last_read_history_id')->nullable()->index();
            }
            if (! Schema::hasColumn('ext_chatbot_participants', 'last_read_at')) {
                $table->timestamp('last_read_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ext_chatbot_participants')) {
            return;
        }

        Schema::table('ext_chatbot_participants', function (Blueprint $table): void {
            foreach (['last_read_history_id', 'last_read_at'] as $column) {
                if (Schema::hasColumn('ext_chatbot_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
