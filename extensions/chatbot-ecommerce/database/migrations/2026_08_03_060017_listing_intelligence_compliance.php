<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_brand_voice_profiles')) Schema::create('ext_chatbot_brand_voice_profiles', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique(); $t->unsignedBigInteger('chatbot_id')->index(); $t->unsignedBigInteger('owner_user_id')->index();
            $t->string('name',191); $t->json('examples')->nullable(); $t->json('tone')->nullable(); $t->json('preferred_terms')->nullable(); $t->json('forbidden_terms')->nullable(); $t->json('guide')->nullable(); $t->string('fingerprint',64)->index(); $t->boolean('active')->default(true)->index(); $t->timestamps();
            $t->unique(['chatbot_id','name'],'brand_voice_name_unique');
        });
        if (! Schema::hasTable('ext_chatbot_product_content_profiles')) Schema::create('ext_chatbot_product_content_profiles', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique(); $t->unsignedBigInteger('chatbot_id')->index(); $t->unsignedBigInteger('owner_user_id')->index(); $t->unsignedBigInteger('product_id')->nullable()->index(); $t->string('sku',191)->nullable()->index();
            $t->string('canonical_name',255); $t->string('brand',191)->nullable(); $t->text('summary')->nullable(); $t->json('features')->nullable(); $t->json('attributes')->nullable(); $t->json('materials')->nullable(); $t->json('dimensions')->nullable(); $t->json('compatibility')->nullable(); $t->json('intended_users')->nullable(); $t->json('contraindications')->nullable(); $t->json('claims')->nullable(); $t->json('evidence_refs')->nullable(); $t->json('semantic_tags')->nullable(); $t->string('content_hash',64)->index(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        if (! Schema::hasTable('ext_chatbot_listing_compliance_rules')) Schema::create('ext_chatbot_listing_compliance_rules', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique(); $t->unsignedBigInteger('chatbot_id')->nullable()->index(); $t->unsignedBigInteger('owner_user_id')->nullable()->index(); $t->string('provider',40)->default('generic')->index(); $t->string('code',100)->index(); $t->string('severity',20)->default('warning')->index(); $t->string('match_type',30)->default('contains'); $t->json('terms')->nullable(); $t->json('field_scopes')->nullable(); $t->text('message'); $t->text('remediation')->nullable(); $t->unsignedInteger('priority')->default(100); $t->boolean('active')->default(true)->index(); $t->json('metadata')->nullable(); $t->timestamps();
            $t->unique(['chatbot_id','provider','code'],'listing_rule_scope_unique');
        });
        if (! Schema::hasTable('ext_chatbot_listing_intelligence_runs')) Schema::create('ext_chatbot_listing_intelligence_runs', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique(); $t->unsignedBigInteger('chatbot_id')->index(); $t->unsignedBigInteger('owner_user_id')->index(); $t->unsignedBigInteger('connection_id')->index(); $t->unsignedBigInteger('listing_snapshot_id')->index(); $t->unsignedBigInteger('product_content_profile_id')->nullable()->index(); $t->unsignedBigInteger('brand_voice_profile_id')->nullable()->index(); $t->string('provider',40)->index(); $t->string('external_listing_id',191)->index(); $t->string('status',40)->default('analysed')->index(); $t->string('source_hash',64)->index(); $t->json('source_content')->nullable(); $t->json('generated_content')->nullable(); $t->json('evidence_report')->nullable(); $t->json('compliance_summary')->nullable(); $t->string('content_hash',64)->nullable()->index(); $t->timestamp('completed_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        if (! Schema::hasTable('ext_chatbot_listing_intelligence_findings')) Schema::create('ext_chatbot_listing_intelligence_findings', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique(); $t->unsignedBigInteger('run_id')->index(); $t->unsignedBigInteger('rule_id')->nullable()->index(); $t->string('code',100)->index(); $t->string('severity',20)->index(); $t->string('field',100)->nullable(); $t->string('matched_value',255)->nullable(); $t->text('message'); $t->text('remediation')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        if (! Schema::hasTable('ext_chatbot_listing_rewrite_proposals')) Schema::create('ext_chatbot_listing_rewrite_proposals', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique(); $t->unsignedBigInteger('run_id')->index(); $t->unsignedBigInteger('chatbot_id')->index(); $t->unsignedBigInteger('owner_user_id')->index(); $t->unsignedBigInteger('marketplace_write_proposal_id')->nullable()->index(); $t->string('status',40)->default('draft')->index(); $t->string('source_hash',64)->index(); $t->string('proposal_hash',64)->index(); $t->json('before_content')->nullable(); $t->json('proposed_content')->nullable(); $t->json('compliance_summary')->nullable(); $t->timestamp('prepared_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { /* Listing audit and evidence records are retained by default. */ }
};
