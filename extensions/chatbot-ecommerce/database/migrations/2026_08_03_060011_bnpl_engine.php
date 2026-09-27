<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_bnpl_provider_profiles')) {
            Schema::create('ext_chatbot_bnpl_provider_profiles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->string('code', 80)->index();
                $table->string('display_name', 160);
                $table->string('provider_type', 80)->default('generic')->index();
                $table->boolean('active')->default(false)->index();
                $table->json('supported_scopes');
                $table->json('supported_shopping_modes');
                $table->json('supported_account_types')->nullable();
                $table->json('supported_currencies')->nullable();
                $table->json('supported_countries')->nullable();
                $table->integer('minimum_amount')->default(1);
                $table->integer('maximum_amount')->nullable();
                $table->json('installment_counts');
                $table->unsignedSmallInteger('interval_days')->default(14);
                $table->unsignedSmallInteger('first_payment_delay_days')->default(0);
                $table->unsignedInteger('merchant_fee_bps')->default(0);
                $table->text('hosted_checkout_url')->nullable();
                $table->text('terms_url')->nullable();
                $table->text('privacy_url')->nullable();
                $table->text('hardship_url')->nullable();
                $table->text('complaints_url')->nullable();
                $table->string('licence_reference', 191)->nullable();
                $table->string('credential_reference', 191)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chatbot_id', 'code'], 'bnpl_profile_chatbot_code_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_bnpl_offers')) {
            Schema::create('ext_chatbot_bnpl_offers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('bnpl_provider_profile_id')->index();
                $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
                $table->unsignedBigInteger('checkout_session_id')->nullable()->index();
                $table->unsignedBigInteger('rental_payment_id')->nullable()->index();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('scope', 40)->index();
                $table->string('shopping_mode', 40)->default('native')->index();
                $table->string('status', 40)->default('quoted')->index();
                $table->integer('amount');
                $table->integer('customer_fee')->default(0);
                $table->integer('merchant_fee')->default(0);
                $table->integer('total_payable');
                $table->char('currency', 3)->index();
                $table->unsignedSmallInteger('installment_count');
                $table->unsignedSmallInteger('interval_days');
                $table->integer('first_installment_amount');
                $table->integer('regular_installment_amount');
                $table->json('installment_schedule');
                $table->json('eligibility_snapshot');
                $table->string('idempotency_key', 191)->unique();
                $table->string('request_hash', 64);
                $table->string('provider_reference', 191)->nullable()->index();
                $table->text('approval_url')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('selected_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'expires_at'], 'bnpl_offer_status_expiry_idx');
            });
        }
    }

    public function down(): void
    {
        // BNPL financial and eligibility records are retained for auditability.
    }
};
