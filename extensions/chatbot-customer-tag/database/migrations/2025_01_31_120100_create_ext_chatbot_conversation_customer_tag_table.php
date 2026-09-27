<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_chatbot_conversation_customer_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('customer_tag_id');
            $table->timestamps();

            $table->unique(['conversation_id', 'customer_tag_id'], 'conversation_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_chatbot_conversation_customer_tag');
    }
};
