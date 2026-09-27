<?php

use App\Extensions\ChatbotVoice\System\Enums\TrainTypeEnum;
use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use App\Models\User;
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
        Schema::create('ext_voicechatbot_trains', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(ExtVoiceChatbot::class, 'chatbot_id')->constrained('ext_voice_chatbots')->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('doc_id')->nullable();
            $table->string('name')->nullable();

            $table->enum('type', TrainTypeEnum::toArray());
            $table->string('file')->nullable();
            $table->text('url')->nullable();
            $table->text('text')->nullable();

            $table->datetime('trained_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_voice_chatbots');
    }
};
