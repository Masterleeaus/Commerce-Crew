<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_stream_events')) {
            Schema::create('ext_chatbot_stream_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_uuid')->unique();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->string('channel', 191)->index();
                $table->string('event', 191)->index();
                $table->json('payload')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
                $table->index(['conversation_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_chatbot_stream_events');
    }
};
