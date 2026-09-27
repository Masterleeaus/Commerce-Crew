<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_chatbot_customer_tags', function (Blueprint $table) {
            $table->id();
            $table->string('tag');
            $table->string('tag_color', 50)->nullable();
            $table->string('background_color', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_chatbot_customer_tags');
    }
};
