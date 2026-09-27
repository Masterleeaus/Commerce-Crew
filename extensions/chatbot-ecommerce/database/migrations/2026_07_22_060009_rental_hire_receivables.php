<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ext_chatbot_rental_accounts')) {
            Schema::create('ext_chatbot_rental_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chatbot_id')->nullable()->index();
                $table->unsignedBigInteger('customer_identity_id')->nullable()->index();
                $table->string('account_number', 100)->unique();
                $table->string('account_type', 40)->default('rent')->index();
                $table->string('display_name', 191);
                $table->char('currency', 3)->default('AUD')->index();
                $table->string('status', 30)->default('active')->index();
                $table->string('access_token_hash', 64)->nullable()->index();
                $table->string('contact_email', 191)->nullable()->index();
                $table->string('contact_phone', 60)->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('closed_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_rental_agreements')) {
            Schema::create('ext_chatbot_rental_agreements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('rental_account_id')->index();
                $table->string('agreement_number', 100)->unique();
                $table->string('agreement_type', 40)->default('rent')->index();
                $table->string('subject_type', 80)->nullable()->index();
                $table->string('subject_reference', 191)->nullable()->index();
                $table->text('subject_description')->nullable();
                $table->string('billing_frequency', 30)->default('monthly')->index();
                $table->unsignedSmallInteger('billing_interval')->default(1);
                $table->unsignedSmallInteger('custom_interval_days')->nullable();
                $table->integer('charge_amount');
                $table->char('currency', 3)->default('AUD')->index();
                $table->date('starts_on')->index();
                $table->date('ends_on')->nullable()->index();
                $table->date('next_charge_date')->nullable()->index();
                $table->smallInteger('due_offset_days')->default(0);
                $table->string('status', 30)->default('draft')->index();
                $table->boolean('allow_partial_payments')->default(true);
                $table->boolean('auto_allocate_payments')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->index(['rental_account_id', 'status', 'next_charge_date'], 'rental_agreement_account_status_next_idx');
            });
        }

        if (! Schema::hasTable('ext_chatbot_rental_agreement_rates')) {
            Schema::create('ext_chatbot_rental_agreement_rates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('rental_agreement_id')->index();
                $table->integer('amount');
                $table->char('currency', 3)->default('AUD');
                $table->date('effective_from')->index();
                $table->date('effective_to')->nullable()->index();
                $table->string('reason', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['rental_agreement_id', 'effective_from'], 'rental_rate_agreement_effective_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_rental_charges')) {
            Schema::create('ext_chatbot_rental_charges', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('rental_account_id')->index();
                $table->unsignedBigInteger('rental_agreement_id')->index();
                $table->string('charge_number', 100)->unique();
                $table->date('period_start')->index();
                $table->date('period_end')->index();
                $table->date('due_date')->index();
                $table->integer('original_amount');
                $table->integer('adjustment_total')->default(0);
                $table->integer('paid_total')->default(0);
                $table->integer('balance_due');
                $table->char('currency', 3)->default('AUD')->index();
                $table->string('status', 30)->default('due')->index();
                $table->string('idempotency_key', 191)->nullable()->unique();
                $table->string('description', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->timestamps();
                $table->unique(['rental_agreement_id', 'period_start'], 'rental_charge_agreement_period_unique');
                $table->index(['rental_account_id', 'status', 'due_date'], 'rental_charge_account_status_due_idx');
            });
        }

        if (! Schema::hasTable('ext_chatbot_rental_payments')) {
            Schema::create('ext_chatbot_rental_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('rental_account_id')->index();
                $table->string('payment_reference', 100)->unique();
                $table->string('idempotency_key', 191)->nullable()->unique();
                $table->integer('amount');
                $table->char('currency', 3)->default('AUD')->index();
                $table->string('method', 60)->index();
                $table->string('provider', 100)->nullable()->index();
                $table->string('provider_payment_id', 191)->nullable()->index();
                $table->text('payment_url')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->json('instructions')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('received_at')->nullable()->index();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
                $table->index(['rental_account_id', 'status', 'received_at'], 'rental_payment_account_status_received_idx');
            });
        }

        if (! Schema::hasTable('ext_chatbot_rental_payment_allocations')) {
            Schema::create('ext_chatbot_rental_payment_allocations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('rental_payment_id')->index();
                $table->unsignedBigInteger('rental_charge_id')->index();
                $table->integer('amount');
                $table->timestamp('allocated_at')->index();
                $table->timestamp('reversed_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['rental_payment_id', 'rental_charge_id'], 'rental_allocation_payment_charge_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_rental_adjustments')) {
            Schema::create('ext_chatbot_rental_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('rental_account_id')->index();
                $table->unsignedBigInteger('rental_charge_id')->nullable()->index();
                $table->string('adjustment_number', 100)->unique();
                $table->string('idempotency_key', 191)->nullable()->unique();
                $table->string('direction', 20)->index();
                $table->integer('amount');
                $table->char('currency', 3)->default('AUD')->index();
                $table->string('reason', 500);
                $table->json('metadata')->nullable();
                $table->timestamp('applied_at')->index();
                $table->timestamp('reversed_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ext_chatbot_rental_receipts')) {
            Schema::create('ext_chatbot_rental_receipts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('rental_account_id')->index();
                $table->unsignedBigInteger('rental_payment_id')->index();
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('receipt_number', 100)->unique();
                $table->string('status', 30)->default('issued')->index();
                $table->integer('amount');
                $table->char('currency', 3)->default('AUD');
                $table->string('receipt_hash', 64)->unique();
                $table->json('data');
                $table->json('metadata')->nullable();
                $table->timestamp('issued_at')->index();
                $table->timestamp('voided_at')->nullable();
                $table->timestamps();
                $table->unique(['rental_payment_id', 'version'], 'rental_receipt_payment_version_unique');
            });
        }

        if (! Schema::hasTable('ext_chatbot_rental_ledger_entries')) {
            Schema::create('ext_chatbot_rental_ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('rental_account_id')->index();
                $table->unsignedBigInteger('rental_agreement_id')->nullable()->index();
                $table->unsignedBigInteger('rental_charge_id')->nullable()->index();
                $table->unsignedBigInteger('rental_payment_id')->nullable()->index();
                $table->unsignedBigInteger('rental_adjustment_id')->nullable()->index();
                $table->string('entry_type', 40)->index();
                $table->integer('debit')->default(0);
                $table->integer('credit')->default(0);
                $table->char('currency', 3)->default('AUD')->index();
                $table->timestamp('effective_at')->index();
                $table->string('reference', 100)->nullable()->index();
                $table->string('description', 500)->nullable();
                $table->string('immutable_hash', 64)->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['rental_account_id', 'effective_at', 'id'], 'rental_ledger_account_effective_idx');
            });
        }
    }

    public function down(): void
    {
        // Financial and receivables records are deliberately preserved for audit safety.
    }
};
