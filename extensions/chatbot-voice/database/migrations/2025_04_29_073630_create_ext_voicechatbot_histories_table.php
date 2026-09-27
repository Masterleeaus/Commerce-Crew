<?php

use App\Extensions\ChatbotVoice\System\Enums\RoleEnum;
use App\Extensions\ChatbotVoice\System\Models\ExtVoicechabotConversation;
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
        Schema::create('ext_voicechatbot_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ExtVoicechabotConversation::class, 'conversation_id')
                ->constrained('ext_voicechabot_conversations')
                ->cascadeOnDelete();

            $table->enum('role', RoleEnum::toArray());
            $table->text('message');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_voicechatbot_histories');
    }
};
