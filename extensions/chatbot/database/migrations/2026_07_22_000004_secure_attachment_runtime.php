<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_attachments')) {
            return;
        }

        Schema::table('ext_chatbot_attachments', function (Blueprint $table): void {
            if (! Schema::hasColumn('ext_chatbot_attachments', 'sha256')) {
                $table->string('sha256', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('ext_chatbot_attachments', 'status')) {
                $table->string('status', 32)->default('ready')->index();
            }
            if (! Schema::hasColumn('ext_chatbot_attachments', 'quarantined_at')) {
                $table->timestamp('quarantined_at')->nullable();
            }
            if (! Schema::hasColumn('ext_chatbot_attachments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        // Additive columns are retained to preserve attachment records on rollback.
    }
};
