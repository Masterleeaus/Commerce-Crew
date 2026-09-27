<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (!Schema::hasTable('ext_chatbot_structured_actions')) return;
  Schema::table('ext_chatbot_structured_actions', function (Blueprint $t) {
   if (!Schema::hasColumn('ext_chatbot_structured_actions','idempotency_key')) $t->string('idempotency_key',191)->nullable();
   if (!Schema::hasColumn('ext_chatbot_structured_actions','rejected_by')) $t->unsignedBigInteger('rejected_by')->nullable();
   if (!Schema::hasColumn('ext_chatbot_structured_actions','rejected_at')) $t->timestamp('rejected_at')->nullable();
   if (!Schema::hasColumn('ext_chatbot_structured_actions','rejection_reason')) $t->text('rejection_reason')->nullable();
   if (!Schema::hasColumn('ext_chatbot_structured_actions','cancelled_at')) $t->timestamp('cancelled_at')->nullable();
   if (!Schema::hasColumn('ext_chatbot_structured_actions','expires_at')) $t->timestamp('expires_at')->nullable();
  });
  try { Schema::table('ext_chatbot_structured_actions', fn (Blueprint $t) => $t->unique(['conversation_id','idempotency_key'],'chatbot_action_idempotency_unique')); } catch (Throwable $e) {}
 }
 public function down(): void {}
};
