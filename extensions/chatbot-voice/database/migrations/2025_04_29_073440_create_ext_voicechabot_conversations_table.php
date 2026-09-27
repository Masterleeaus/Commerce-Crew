<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ext_voicechabot_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('chatbot_uuid');
            $table->foreign('chatbot_uuid')
                ->references('uuid')
                ->on('ext_voice_chatbots')
                ->cascadeOnDelete();
            $table->string('conversation_id');
            $table->string('status')->default('processing');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_voicechabot_conversations');
    }
};
