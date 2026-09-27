<?php

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
        Schema::create('ext_voice_chatbots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('agent_id');
            $table->string('title');
            $table->string('bubble_message');
            $table->string('welcome_message');
            $table->text('instructions');
            $table->string('language')->nullable();

            $table->string('ai_model')->nullable();
            $table->string('avatar')->nullable();
            $table->string('voice_id');
            $table->string('position')->default('right');
            $table->boolean('active')->default(true);
            $table->boolean('is_favorite')->default(false);

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
