<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAccount;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAdjustment;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAgreement;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAgreementRate;
use App\Extensions\ChatbotEcommerce\System\Models\RentalCharge;
use App\Extensions\ChatbotEcommerce\System\Models\RentalLedgerEntry;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPayment;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPaymentAllocation;
use App\Extensions\ChatbotEcommerce\System\Models\RentalReceipt;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use App\Extensions\ChatbotEcommerce\System\Support\RentalAllocationCalculator;
use App\Extensions\ChatbotEcommerce\System\Support\RentalBillingSchedule;
use App\Extensions\ChatbotEcommerce\System\Support\RentalHireStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RentalHireRuntime
{
    /** @return array{account:RentalAccount,access_token:string} */
    public function createAccount(array $attributes): array
    {
        $currency = strtoupper(trim((string) ($attributes['currency'] ?? 'AUD')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['currency' => 'Currency must use a three-letter ISO code.']);
        }

        $token = Str::random(64);
        $account = RentalAccount::query()->create([
            'chatbot_id' => $attributes['chatbot_id'] ?? null,
            'customer_identity_id' => $attributes['customer_identity_id'] ?? null,
            'account_number' => $attributes['account_number'] ?? $this->reference('account_prefix', 'RHA'),
            'account_type' => strtolower(trim((string) ($attributes['account_type'] ?? 'rent'))),
            'display_name' => trim((string) $attributes['display_name']),
            'currency' => $currency,
            'status' => RentalHireStatus::ACCOUNT_ACTIVE,
            'access_token_hash' => hash('sha256', $token),
            'contact_email' => $attributes['contact_email'] ?? null,
            'contact_phone' => $attributes['contact_phone'] ?? null,
            'metadata' => $attributes['metadata'] ?? [],
        ]);

        Metrics::increment('rental_hire.account.created');
        event(new ExtensionEvent('rental_hire.account.created', ['account_uuid' => $account->uuid]));

        return ['account' => $account, 'access_token' => $token];
    }

    /** @return array{account:RentalAccount,access_token:string} */
    public function rotateAccessToken(RentalAccount $account): array
    {
        $token = Str::random(64);
        $account->forceFill(['access_token_hash' => hash('sha256', $token)])->save();
        return ['account' => $account->refresh(), 'access_token' => $token];
    }

    public function authenticate(RentalAccount $account, ?string $token): bool
    {
        $candidate = trim((string) $token);
        return $candidate !== ''
            && is_string($account->access_token_hash)
            && hash_equals($account->access_token_hash, hash('sha256', $candidate));
    }

    public function createAgreement(RentalAccount $account, array $attributes): RentalAgreement
    {
        if ((string) $account->status !== RentalHireStatus::ACCOUNT_ACTIVE) {
            throw ValidationException::withMessages(['account' => 'The rental or hire account is not active.']);
        }

        $frequency = strtolower(trim((string) ($attributes['billing_frequency'] ?? 'monthly')));
        if (! in_array($frequency, RentalBillingSchedule::FREQUENCIES, true)) {
            throw ValidationException::withMessages(['billing_frequency' => 'The billing frequency is unsupported.']);
        }
        $amount = (int) ($attributes['charge_amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['charge_amount' => 'The recurring charge must be greater than zero.']);
        }
        $currency = strtoupper(trim((string) ($attributes['currency'] ?? $account->currency)));
        if ($currency !== strtoupper((string) $account->currency)) {
            throw ValidationException::withMessages(['currency' => 'Agreement currency must match the account currency.']);
        }

        return DB::transaction(function () use ($account, $attributes, $frequency, $amount, $currency): RentalAgreement {
            $startsOn = Carbon::parse((string) $attributes['starts_on'])->toDateString();
            $agreement = RentalAgreement::query()->create([
                'rental_account_id' => $account->id,
                'agreement_number' => $attributes['agreement_number'] ?? $this->reference('agreement_prefix', 'AGR'),
                'agreement_type' => strtolower(trim((string) ($attributes['agreement_type'] ?? $account->account_type))),
                'subject_type' => $attributes['subject_type'] ?? null,
                'subject_reference' => $attributes['subject_reference'] ?? null,
                'subject_description' => $attributes['subject_description'] ?? null,
                'billing_frequency' => $frequency,
                'billing_interval' => max(1, (int) ($attributes['billing_interval'] ?? 1)),
                'custom_interval_days' => $attributes['custom_interval_days'] ?? null,
                'charge_amount' => $amount,
                'currency' => $currency,
                'starts_on' => $startsOn,
                'ends_on' => isset($attributes['ends_on']) ? Carbon::parse((string) $attributes['ends_on'])->toDateString() : null,
                'next_charge_date' => $startsOn,
                'due_offset_days' => max(-365, min(365, (int) ($attributes['due_offset_days'] ?? 0))),
                'status' => (string) ($attributes['status'] ?? RentalHireStatus::AGREEMENT_ACTIVE),
                'allow_partial_payments' => (bool) ($attributes['allow_partial_payments'] ?? true),
                'auto_allocate_payments' => (bool) ($attributes['auto_allocate_payments'] ?? true),
                'metadata' => $attributes['metadata'] ?? [],
                'activated_at' => (($attributes['status'] ?? RentalHireStatus::AGREEMENT_ACTIVE) === RentalHireStatus::AGREEMENT_ACTIVE) ? now() : null,
            ]);

            RentalAgreementRate::query()->create([
                'rental_agreement_id' => $agreement->id,
                'amount' => $amount,
                'currency' => $currency,
                'effective_from' => $startsOn,
                'reason' => 'Initial agreement rate',
                'metadata' => [],
            ]);

            Metrics::increment('rental_hire.agreement.created', ['type' => $agreement->agreement_type]);
            event(new ExtensionEvent('rental_hire.agreement.created', ['agreement_uuid' => $agreement->uuid, 'account_uuid' => $account->uuid]));
            return $agreement->load('rates');
        });
    }

    public function addRate(RentalAgreement $agreement, int $amount, string $effectiveFrom, ?string $reason = null, array $metadata = []): RentalAgreementRate
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The rate must be greater than zero.']);
        }

        return DB::transaction(function () use ($agreement, $amount, $effectiveFrom, $reason, $metadata): RentalAgreementRate {
            $locked = RentalAgreement::query()->whereKey($agreement->id)->lockForUpdate()->firstOrFail();
            $date = Carbon::parse($effectiveFrom)->toDateString();
            if ($date < $locked->starts_on->toDateString()) {
                throw ValidationException::withMessages(['effective_from' => 'A rate cannot begin before the agreement.']);
            }

            RentalAgreementRate::query()
                ->where('rental_agreement_id', $locked->id)
                ->where('effective_from', '<', $date)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
                ->update(['effective_to' => Carbon::parse($date)->subDay()->toDateString(), 'updated_at' => now()]);

            $nextRate = RentalAgreementRate::query()
                ->where('rental_agreement_id', $locked->id)
                ->where('effective_from', '>', $date)
                ->orderBy('effective_from')
                ->first();
            $effectiveTo = $nextRate ? $nextRate->effective_from->copy()->subDay()->toDateString() : null;
            $rate = RentalAgreementRate::query()->updateOrCreate(
                ['rental_agreement_id' => $locked->id, 'effective_from' => $date],
                ['amount' => $amount, 'currency' => $locked->currency, 'effective_to' => $effectiveTo, 'reason' => $reason, 'metadata' => $metadata],
            );

            $locked->forceFill(['charge_amount' => $amount])->save();
            event(new ExtensionEvent('rental_hire.rate.changed', ['agreement_uuid' => $locked->uuid, 'rate_uuid' => $rate->uuid]));
            return $rate;
        });
    }

    /** @return Collection<int,RentalCharge> */
    public function generateCharges(RentalAgreement $agreement, ?string $throughDate = null): Collection
    {
        return DB::transaction(function () use ($agreement, $throughDate): Collection {
            $locked = RentalAgreement::query()->whereKey($agreement->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== RentalHireStatus::AGREEMENT_ACTIVE || ! $locked->next_charge_date) {
                return new Collection();
            }

            $through = $throughDate ?: now()->addDays((int) config('chatbot-ecommerce.rental_hire.charge_generation_horizon_days', 30))->toDateString();
            $periods = RentalBillingSchedule::periods(
                $locked->next_charge_date->toDateString(),
                Carbon::parse($through)->toDateString(),
                (string) $locked->billing_frequency,
                (int) $locked->billing_interval,
                (int) $locked->due_offset_days,
                $locked->ends_on?->toDateString(),
                (int) config('chatbot-ecommerce.rental_hire.maximum_generation_periods', 500),
                $locked->custom_interval_days ? (int) $locked->custom_interval_days : null,
            );

            $created = new Collection();
            foreach ($periods as $period) {
                $existing = RentalCharge::query()
                    ->where('rental_agreement_id', $locked->id)
                    ->where('period_start', $period['period_start'])
                    ->first();
                if ($existing) {
                    $created->push($existing);
                    continue;
                }

                $amount = $this->rateFor($locked, $period['period_start']);
                $charge = RentalCharge::query()->create([
                    'rental_account_id' => $locked->rental_account_id,
                    'rental_agreement_id' => $locked->id,
                    'charge_number' => $this->reference('charge_prefix', 'CHG'),
                    'period_start' => $period['period_start'],
                    'period_end' => $period['period_end'],
                    'due_date' => $period['due_date'],
                    'original_amount' => $amount,
                    'adjustment_total' => 0,
                    'paid_total' => 0,
                    'balance_due' => $amount,
                    'currency' => $locked->currency,
                    'status' => Carbon::parse($period['due_date'])->lt(now()->startOfDay()) ? RentalHireStatus::CHARGE_OVERDUE : RentalHireStatus::CHARGE_DUE,
                    'idempotency_key' => $locked->uuid . ':' . $period['period_start'],
                    'description' => $locked->subject_description ?: ('Rental/hire charge for ' . $period['period_start'] . ' to ' . $period['period_end']),
                    'metadata' => ['agreement_type' => $locked->agreement_type, 'subject_reference' => $locked->subject_reference],
                ]);

                $this->writeLedger($locked->account()->firstOrFail(), [
                    'rental_agreement_id' => $locked->id,
                    'rental_charge_id' => $charge->id,
                    'entry_type' => 'charge',
                    'debit' => $amount,
                    'credit' => 0,
                    'currency' => $locked->currency,
                    'effective_at' => Carbon::parse($period['due_date'])->startOfDay(),
                    'reference' => $charge->charge_number,
                    'description' => $charge->description,
                    'metadata' => ['period_start' => $period['period_start'], 'period_end' => $period['period_end']],
                ]);
                $created->push($charge);
            }

            if ($created->isNotEmpty()) {
                $last = $created->sortBy('period_start')->last();
                $next = Carbon::parse($last->period_end)->addDay();
                $ended = (string) $locked->billing_frequency === 'one_time' || ($locked->ends_on && $next->gt($locked->ends_on));
                $locked->forceFill([
                    'next_charge_date' => $ended ? null : $next->toDateString(),
                    'status' => $ended ? RentalHireStatus::AGREEMENT_ENDED : $locked->status,
                    'ended_at' => $ended ? now() : $locked->ended_at,
                ])->save();
            }

            Metrics::increment('rental_hire.charges.generated', ['count' => $created->count()]);
            return $created;
        });
    }

    public function createPaymentRequest(RentalAccount $account, int $amount, string $method, string $idempotencyKey, array $attributes = []): RentalPayment
    {
        $key = $this->requiredKey($idempotencyKey);
        if ((string) $account->status !== RentalHireStatus::ACCOUNT_ACTIVE) {
            throw ValidationException::withMessages(['account' => 'The rental or hire account is not active.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }
        $method = strtolower(trim($method));
        $allowed = array_map('strval', (array) config('chatbot-ecommerce.rental_hire.allowed_payment_methods', ['bank_transfer', 'payid', 'cash', 'external']));
        if (! in_array($method, $allowed, true)) {
            throw ValidationException::withMessages(['method' => 'The selected rental or hire payment method is not enabled.']);
        }

        $requestHash = hash('sha256', json_encode($this->canonicalise([$account->uuid, $amount, $method, $attributes]), JSON_THROW_ON_ERROR));
        if ($existing = RentalPayment::query()->where('idempotency_key', $key)->first()) {
            if ((int) $existing->rental_account_id !== (int) $account->id || ! hash_equals((string) (($existing->metadata ?? [])['request_hash'] ?? ''), $requestHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for a different payment request.']);
            }
            return $existing;
        }

        $instructions = array_replace_recursive(
            (array) config('chatbot-ecommerce.rental_hire.payment_instructions.' . $method, []),
            (array) ($attributes['instructions'] ?? []),
        );

        $payment = RentalPayment::query()->create([
            'rental_account_id' => $account->id,
            'payment_reference' => $attributes['payment_reference'] ?? $this->reference('payment_prefix', 'PAY'),
            'idempotency_key' => $key,
            'amount' => $amount,
            'currency' => $account->currency,
            'method' => $method,
            'provider' => $attributes['provider'] ?? null,
            'provider_payment_id' => $attributes['provider_payment_id'] ?? null,
            'payment_url' => $attributes['payment_url'] ?? null,
            'status' => RentalHireStatus::PAYMENT_PENDING,
            'instructions' => $instructions,
            'metadata' => array_replace_recursive((array) ($attributes['metadata'] ?? []), ['request_hash' => $requestHash]),
            'requested_at' => now(),
            'expires_at' => now()->addMinutes((int) config('chatbot-ecommerce.rental_hire.payment_request_ttl_minutes', 1440)),
        ]);

        Metrics::increment('rental_hire.payment.requested', ['method' => $method]);
        event(new ExtensionEvent('rental_hire.payment.requested', ['payment_uuid' => $payment->uuid, 'account_uuid' => $account->uuid]));
        return $payment;
    }

    public function confirmPayment(RentalPayment $payment, string $idempotencyKey, array $attributes = []): RentalPayment
    {
        $key = $this->requiredKey($idempotencyKey);
        return DB::transaction(function () use ($payment, $key, $attributes): RentalPayment {
            $locked = RentalPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $metadata = (array) ($locked->metadata ?? []);
            if ((string) $locked->status === RentalHireStatus::PAYMENT_RECEIVED) {
                if (isset($metadata['confirmation_key_hash']) && ! hash_equals((string) $metadata['confirmation_key_hash'], hash('sha256', $key))) {
                    throw ValidationException::withMessages(['idempotency_key' => 'The payment was confirmed using another idempotency key.']);
                }
                return $locked->load(['allocations.charge', 'receipt']);
            }
            if (in_array((string) $locked->status, [RentalHireStatus::PAYMENT_FAILED, RentalHireStatus::PAYMENT_REVERSED], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment can no longer be confirmed.']);
            }
            if ($locked->expires_at && $locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['payment' => 'This payment request has expired.']);
            }

            $metadata['confirmation_key_hash'] = hash('sha256', $key);
            $locked->forceFill([
                'status' => RentalHireStatus::PAYMENT_RECEIVED,
                'provider' => $attributes['provider'] ?? $locked->provider,
                'provider_payment_id' => $attributes['provider_payment_id'] ?? $locked->provider_payment_id,
                'received_at' => isset($attributes['received_at']) ? Carbon::parse((string) $attributes['received_at']) : now(),
                'expires_at' => null,
                'metadata' => $metadata,
            ])->save();

            $account = RentalAccount::query()->whereKey($locked->rental_account_id)->lockForUpdate()->firstOrFail();
            $this->writeLedger($account, [
                'rental_payment_id' => $locked->id,
                'entry_type' => 'payment',
                'debit' => 0,
                'credit' => (int) $locked->amount,
                'currency' => $locked->currency,
                'effective_at' => $locked->received_at,
                'reference' => $locked->payment_reference,
                'description' => 'Rental/hire payment received',
                'metadata' => ['method' => $locked->method, 'provider' => $locked->provider],
            ]);

            $requestedAllocations = isset($attributes['allocations']) ? (array) $attributes['allocations'] : null;
            $autoAllocate = (bool) ($attributes['auto_allocate'] ?? true);
            if ($requestedAllocations !== null || $autoAllocate) {
                $this->allocateLockedPayment($locked, $requestedAllocations);
            }
            $this->issueReceipt($locked);

            Metrics::increment('rental_hire.payment.received', ['method' => $locked->method]);
            event(new ExtensionEvent('rental_hire.payment.received', ['payment_uuid' => $locked->uuid, 'account_uuid' => $account->uuid]));
            return $locked->refresh()->load(['allocations.charge', 'receipt']);
        });
    }

    public function allocatePayment(RentalPayment $payment, ?array $allocations = null, ?string $idempotencyKey = null): RentalPayment
    {
        return DB::transaction(function () use ($payment, $allocations, $idempotencyKey): RentalPayment {
            $locked = RentalPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== RentalHireStatus::PAYMENT_RECEIVED) {
                throw ValidationException::withMessages(['payment' => 'Only received payments can be allocated.']);
            }

            if ($idempotencyKey !== null) {
                $key = $this->requiredKey($idempotencyKey);
                $hash = hash('sha256', json_encode($this->canonicalise($allocations ?? ['auto']), JSON_THROW_ON_ERROR));
                $metadata = (array) ($locked->metadata ?? []);
                $operations = (array) ($metadata['allocation_operations'] ?? []);
                if (isset($operations[$key])) {
                    if (! hash_equals((string) $operations[$key], $hash)) {
                        throw ValidationException::withMessages(['idempotency_key' => 'The allocation key was already used for a different request.']);
                    }
                    return $locked->load(['allocations.charge', 'receipt']);
                }
                $operations[$key] = $hash;
                $metadata['allocation_operations'] = array_slice($operations, -100, null, true);
                $locked->forceFill(['metadata' => $metadata])->save();
            }

            $this->allocateLockedPayment($locked, $allocations);
            if ($locked->receipt()->exists()) {
                $this->issueReceipt($locked);
            }
            return $locked->refresh()->load(['allocations.charge', 'receipt']);
        });
    }

    public function reversePayment(RentalPayment $payment, string $reason, ?string $idempotencyKey = null): RentalPayment
    {
        return DB::transaction(function () use ($payment, $reason, $idempotencyKey): RentalPayment {
            $locked = RentalPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === RentalHireStatus::PAYMENT_REVERSED) {
                return $locked->load(['allocations.charge', 'receipt']);
            }
            if ((string) $locked->status !== RentalHireStatus::PAYMENT_RECEIVED) {
                throw ValidationException::withMessages(['payment' => 'Only received payments can be reversed.']);
            }

            $allocations = RentalPaymentAllocation::query()->where('rental_payment_id', $locked->id)->whereNull('reversed_at')->lockForUpdate()->get();
            foreach ($allocations as $allocation) {
                $allocation->forceFill(['reversed_at' => now(), 'metadata' => array_replace((array) $allocation->metadata, ['reversal_reason' => $reason])])->save();
                $this->syncCharge($allocation->charge()->firstOrFail());
            }

            $metadata = array_replace((array) $locked->metadata, ['reversal_reason' => $reason]);
            if ($idempotencyKey !== null) {
                $metadata['reversal_key_hash'] = hash('sha256', $this->requiredKey($idempotencyKey));
            }
            $locked->forceFill(['status' => RentalHireStatus::PAYMENT_REVERSED, 'reversed_at' => now(), 'metadata' => $metadata])->save();

            $account = RentalAccount::query()->whereKey($locked->rental_account_id)->lockForUpdate()->firstOrFail();
            $this->writeLedger($account, [
                'rental_payment_id' => $locked->id,
                'entry_type' => 'payment_reversal',
                'debit' => (int) $locked->amount,
                'credit' => 0,
                'currency' => $locked->currency,
                'effective_at' => now(),
                'reference' => $locked->payment_reference,
                'description' => 'Rental/hire payment reversed',
                'metadata' => ['reason' => $reason],
            ]);

            if ($receipt = $locked->receipt()->first()) {
                $receipt->forceFill(['status' => RentalHireStatus::RECEIPT_VOID, 'voided_at' => now(), 'metadata' => array_replace((array) $receipt->metadata, ['void_reason' => $reason])])->save();
            }
            event(new ExtensionEvent('rental_hire.payment.reversed', ['payment_uuid' => $locked->uuid, 'account_uuid' => $account->uuid]));
            return $locked->refresh()->load(['allocations.charge', 'receipt']);
        });
    }

    public function createAdjustment(RentalAccount $account, string $direction, int $amount, string $reason, ?RentalCharge $charge = null, ?string $idempotencyKey = null, array $metadata = []): RentalAdjustment
    {
        $direction = strtolower(trim($direction));
        if (! in_array($direction, ['debit', 'credit'], true)) {
            throw ValidationException::withMessages(['direction' => 'Adjustment direction must be debit or credit.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Adjustment amount must be greater than zero.']);
        }
        if ($charge && (int) $charge->rental_account_id !== (int) $account->id) {
            throw ValidationException::withMessages(['charge' => 'The charge belongs to another rental account.']);
        }

        return DB::transaction(function () use ($account, $direction, $amount, $reason, $charge, $idempotencyKey, $metadata): RentalAdjustment {
            $key = $idempotencyKey ? $this->requiredKey($idempotencyKey) : null;
            if ($key && ($existing = RentalAdjustment::query()->where('idempotency_key', $key)->first())) {
                if ((int) $existing->rental_account_id !== (int) $account->id
                    || (string) $existing->direction !== $direction
                    || (int) $existing->amount !== $amount
                    || (string) $existing->reason !== trim($reason)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'The adjustment key was already used for another request.']);
                }
                return $existing;
            }

            $adjustment = RentalAdjustment::query()->create([
                'rental_account_id' => $account->id,
                'rental_charge_id' => $charge?->id,
                'adjustment_number' => $this->reference('adjustment_prefix', 'ADJ'),
                'idempotency_key' => $key,
                'direction' => $direction,
                'amount' => $amount,
                'currency' => $account->currency,
                'reason' => trim($reason),
                'metadata' => $metadata,
                'applied_at' => now(),
            ]);

            $this->writeLedger($account, [
                'rental_charge_id' => $charge?->id,
                'rental_adjustment_id' => $adjustment->id,
                'entry_type' => 'adjustment',
                'debit' => $direction === 'debit' ? $amount : 0,
                'credit' => $direction === 'credit' ? $amount : 0,
                'currency' => $account->currency,
                'effective_at' => $adjustment->applied_at,
                'reference' => $adjustment->adjustment_number,
                'description' => $reason,
                'metadata' => ['direction' => $direction],
            ]);
            if ($charge) {
                $this->syncCharge($charge);
            }

            event(new ExtensionEvent('rental_hire.adjustment.created', ['adjustment_uuid' => $adjustment->uuid, 'account_uuid' => $account->uuid]));
            return $adjustment;
        });
    }

    /** @return array<string,mixed> */
    public function accountSummary(RentalAccount $account): array
    {
        RentalCharge::query()->where('rental_account_id', $account->id)->where('balance_due', '>', 0)->whereIn('status', [RentalHireStatus::CHARGE_DUE, RentalHireStatus::CHARGE_PARTIAL])->where('due_date', '<', now()->toDateString())->update(['status' => RentalHireStatus::CHARGE_OVERDUE, 'updated_at' => now()]);
        $charges = RentalCharge::query()->where('rental_account_id', $account->id)->where('status', '<>', RentalHireStatus::CHARGE_VOID);
        $outstanding = (int) (clone $charges)->sum('balance_due');
        $arrears = (int) (clone $charges)->where('due_date', '<', now()->toDateString())->where('balance_due', '>', 0)->sum('balance_due');
        $ledgerBalance = (int) RentalLedgerEntry::query()->where('rental_account_id', $account->id)->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS balance')->value('balance');
        $received = RentalPayment::query()->where('rental_account_id', $account->id)->where('status', RentalHireStatus::PAYMENT_RECEIVED)->get();
        $unallocated = 0;
        foreach ($received as $payment) {
            $allocated = (int) RentalPaymentAllocation::query()->where('rental_payment_id', $payment->id)->whereNull('reversed_at')->sum('amount');
            $unallocated += max(0, (int) $payment->amount - $allocated);
        }
        $next = RentalCharge::query()->where('rental_account_id', $account->id)->where('balance_due', '>', 0)->where('status', '<>', RentalHireStatus::CHARGE_VOID)->orderBy('due_date')->first();

        return [
            'account_uuid' => $account->uuid,
            'account_number' => $account->account_number,
            'account_type' => $account->account_type,
            'display_name' => $account->display_name,
            'currency' => $account->currency,
            'status' => $account->status,
            'outstanding_balance' => $outstanding,
            'arrears_balance' => $arrears,
            'ledger_balance' => $ledgerBalance,
            'credit_balance' => max(0, -$ledgerBalance),
            'unallocated_credit' => $unallocated,
            'next_due' => $next ? [
                'charge_uuid' => $next->uuid,
                'due_date' => $next->due_date?->toDateString(),
                'balance_due' => (int) $next->balance_due,
                'period_start' => $next->period_start?->toDateString(),
                'period_end' => $next->period_end?->toDateString(),
            ] : null,
            'active_agreements' => RentalAgreement::query()->where('rental_account_id', $account->id)->where('status', RentalHireStatus::AGREEMENT_ACTIVE)->count(),
        ];
    }

    /** @return array{entries:list<array<string,mixed>>,closing_balance:int,currency:string} */
    public function ledger(RentalAccount $account, int $limit = 200): array
    {
        $entries = RentalLedgerEntry::query()
            ->where('rental_account_id', $account->id)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->limit(min(max($limit, 1), 1000))
            ->get()
            ->sortBy([['effective_at', 'asc'], ['id', 'asc']])
            ->values();
        $closingBalance = (int) RentalLedgerEntry::query()
            ->where('rental_account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS balance')
            ->value('balance');
        $selectedMovement = (int) $entries->sum(fn (RentalLedgerEntry $entry): int => (int) $entry->debit - (int) $entry->credit);
        $balance = $closingBalance - $selectedMovement;
        $rows = [];
        foreach ($entries as $entry) {
            $balance += (int) $entry->debit - (int) $entry->credit;
            $rows[] = [
                'uuid' => $entry->uuid,
                'entry_type' => $entry->entry_type,
                'effective_at' => $entry->effective_at?->toIso8601String(),
                'reference' => $entry->reference,
                'description' => $entry->description,
                'debit' => (int) $entry->debit,
                'credit' => (int) $entry->credit,
                'balance' => $balance,
                'currency' => $entry->currency,
                'metadata' => $entry->metadata ?? [],
            ];
        }
        return ['entries' => $rows, 'closing_balance' => $closingBalance, 'currency' => (string) $account->currency];
    }

    public function receipt(RentalPayment $payment): RentalReceipt
    {
        if ((string) $payment->status !== RentalHireStatus::PAYMENT_RECEIVED) {
            throw ValidationException::withMessages(['payment' => 'A receipt is available only for a received payment.']);
        }
        return DB::transaction(fn (): RentalReceipt => $this->issueReceipt(RentalPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail()));
    }

    public function generateDueCharges(?string $throughDate = null, int $limit = 500): int
    {
        RentalCharge::query()->where('balance_due', '>', 0)->whereIn('status', [RentalHireStatus::CHARGE_DUE, RentalHireStatus::CHARGE_PARTIAL])->where('due_date', '<', now()->toDateString())->update(['status' => RentalHireStatus::CHARGE_OVERDUE, 'updated_at' => now()]);
        $through = $throughDate ?: now()->addDays((int) config('chatbot-ecommerce.rental_hire.charge_generation_horizon_days', 30))->toDateString();
        $agreements = RentalAgreement::query()
            ->where('status', RentalHireStatus::AGREEMENT_ACTIVE)
            ->whereNotNull('next_charge_date')
            ->where('next_charge_date', '<=', $through)
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->get();
        $count = 0;
        foreach ($agreements as $agreement) {
            $count += $this->generateCharges($agreement, $through)->count();
        }
        return $count;
    }

    public function expirePaymentRequests(int $limit = 500): int
    {
        $ids = RentalPayment::query()
            ->where('status', RentalHireStatus::PAYMENT_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->pluck('id');
        return RentalPayment::query()->whereIn('id', $ids)->where('status', RentalHireStatus::PAYMENT_PENDING)->update([
            'status' => RentalHireStatus::PAYMENT_FAILED,
            'failed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function allocateLockedPayment(RentalPayment $payment, ?array $requested): void
    {
        $allocated = (int) RentalPaymentAllocation::query()->where('rental_payment_id', $payment->id)->whereNull('reversed_at')->sum('amount');
        $available = max(0, (int) $payment->amount - $allocated);
        if ($available === 0) {
            return;
        }

        if ($requested === null) {
            $charges = RentalCharge::query()
                ->where('rental_account_id', $payment->rental_account_id)
                ->where('balance_due', '>', 0)
                ->where('status', '<>', RentalHireStatus::CHARGE_VOID)
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $plan = RentalAllocationCalculator::allocate($available, $charges->map(fn (RentalCharge $charge) => [
                'uuid' => $charge->uuid, 'id' => $charge->id, 'balance_due' => (int) $charge->balance_due, 'due_date' => $charge->due_date?->toDateString(),
            ])->all())['allocations'];
        } else {
            $plan = [];
            $requestedTotal = 0;
            foreach ($requested as $row) {
                $amount = (int) ($row['amount'] ?? 0);
                $uuid = trim((string) ($row['charge_uuid'] ?? ''));
                if ($amount <= 0 || $uuid === '') {
                    throw ValidationException::withMessages(['allocations' => 'Each allocation requires a charge UUID and positive amount.']);
                }
                $requestedTotal += $amount;
                $plan[] = ['charge_uuid' => $uuid, 'amount' => $amount];
            }
            if ($requestedTotal > $available) {
                throw ValidationException::withMessages(['allocations' => 'Allocations exceed the unallocated payment amount.']);
            }
        }

        foreach ($plan as $row) {
            $charge = RentalCharge::query()->where('uuid', $row['charge_uuid'])->lockForUpdate()->firstOrFail();
            if ((int) $charge->rental_account_id !== (int) $payment->rental_account_id || strtoupper((string) $charge->currency) !== strtoupper((string) $payment->currency)) {
                throw ValidationException::withMessages(['allocations' => 'A charge belongs to another account or currency.']);
            }
            $amount = min((int) $row['amount'], (int) $charge->balance_due, $available);
            if ($amount <= 0) {
                continue;
            }
            $agreement = $charge->agreement()->first();
            if ($agreement && ! $agreement->allow_partial_payments && $amount < (int) $charge->balance_due) {
                throw ValidationException::withMessages(['allocations' => 'This agreement does not permit partial payments.']);
            }

            $existing = RentalPaymentAllocation::query()
                ->where('rental_payment_id', $payment->id)
                ->where('rental_charge_id', $charge->id)
                ->lockForUpdate()
                ->first();
            if ($existing && $existing->reversed_at === null) {
                $existing->increment('amount', $amount);
            } else {
                RentalPaymentAllocation::query()->updateOrCreate(
                    ['rental_payment_id' => $payment->id, 'rental_charge_id' => $charge->id],
                    ['uuid' => $existing?->uuid ?: (string) Str::uuid(), 'amount' => $amount, 'allocated_at' => now(), 'reversed_at' => null, 'metadata' => []],
                );
            }
            $available -= $amount;
            $this->syncCharge($charge);
            if ($available <= 0) {
                break;
            }
        }
    }

    private function syncCharge(RentalCharge $charge): RentalCharge
    {
        $locked = RentalCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();
        if ((string) $locked->status === RentalHireStatus::CHARGE_VOID) {
            return $locked;
        }
        $paid = (int) RentalPaymentAllocation::query()->where('rental_charge_id', $locked->id)->whereNull('reversed_at')->sum('amount');
        $debits = (int) RentalAdjustment::query()->where('rental_charge_id', $locked->id)->whereNull('reversed_at')->where('direction', 'debit')->sum('amount');
        $credits = (int) RentalAdjustment::query()->where('rental_charge_id', $locked->id)->whereNull('reversed_at')->where('direction', 'credit')->sum('amount');
        $adjustment = $debits - $credits;
        $balance = max(0, (int) $locked->original_amount + $adjustment - $paid);
        $status = match (true) {
            $balance === 0 => RentalHireStatus::CHARGE_PAID,
            $paid > 0 => RentalHireStatus::CHARGE_PARTIAL,
            $locked->due_date && $locked->due_date->lt(now()->startOfDay()) => RentalHireStatus::CHARGE_OVERDUE,
            default => RentalHireStatus::CHARGE_DUE,
        };
        $locked->forceFill([
            'adjustment_total' => $adjustment,
            'paid_total' => $paid,
            'balance_due' => $balance,
            'status' => $status,
            'paid_at' => $balance === 0 ? ($locked->paid_at ?: now()) : null,
        ])->save();
        return $locked;
    }

    private function issueReceipt(RentalPayment $payment): RentalReceipt
    {
        $payment->load(['account', 'allocations.charge.agreement']);
        $allocations = $payment->allocations->whereNull('reversed_at')->map(function (RentalPaymentAllocation $allocation): array {
            $charge = $allocation->charge;
            return [
                'charge_uuid' => $charge?->uuid,
                'charge_number' => $charge?->charge_number,
                'amount' => (int) $allocation->amount,
                'period_start' => $charge?->period_start?->toDateString(),
                'period_end' => $charge?->period_end?->toDateString(),
                'due_date' => $charge?->due_date?->toDateString(),
                'subject_reference' => $charge?->agreement?->subject_reference,
            ];
        })->values()->all();
        $allocated = array_sum(array_column($allocations, 'amount'));
        $data = [
            'account_number' => $payment->account?->account_number,
            'account_name' => $payment->account?->display_name,
            'payment_reference' => $payment->payment_reference,
            'payment_date' => $payment->received_at?->toIso8601String(),
            'payment_method' => $payment->method,
            'amount' => (int) $payment->amount,
            'currency' => $payment->currency,
            'allocated_amount' => $allocated,
            'unallocated_credit' => max(0, (int) $payment->amount - $allocated),
            'allocations' => $allocations,
        ];
        $hash = hash('sha256', json_encode($this->canonicalise($data), JSON_THROW_ON_ERROR));
        if ($existing = RentalReceipt::query()->where('rental_payment_id', $payment->id)->where('receipt_hash', $hash)->first()) {
            return $existing;
        }
        $version = (int) RentalReceipt::query()->where('rental_payment_id', $payment->id)->max('version') + 1;
        return RentalReceipt::query()->create([
            'rental_account_id' => $payment->rental_account_id,
            'rental_payment_id' => $payment->id,
            'version' => max(1, $version),
            'receipt_number' => $this->reference('receipt_prefix', 'RCP'),
            'status' => RentalHireStatus::RECEIPT_ISSUED,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'receipt_hash' => $hash,
            'data' => $data,
            'metadata' => ['supersedes_version' => $version > 1 ? $version - 1 : null],
            'issued_at' => now(),
        ]);
    }

    private function rateFor(RentalAgreement $agreement, string $periodStart): int
    {
        $rate = RentalAgreementRate::query()
            ->where('rental_agreement_id', $agreement->id)
            ->where('effective_from', '<=', $periodStart)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $periodStart))
            ->orderByDesc('effective_from')
            ->first();
        return max(0, (int) ($rate?->amount ?? $agreement->charge_amount));
    }

    private function writeLedger(RentalAccount $account, array $attributes): RentalLedgerEntry
    {
        $uuid = (string) Str::uuid();
        $payload = array_replace([
            'uuid' => $uuid,
            'rental_account_id' => $account->id,
            'rental_agreement_id' => null,
            'rental_charge_id' => null,
            'rental_payment_id' => null,
            'rental_adjustment_id' => null,
            'debit' => 0,
            'credit' => 0,
            'currency' => $account->currency,
            'effective_at' => now(),
            'reference' => null,
            'description' => null,
            'metadata' => [],
        ], $attributes);
        $payload['immutable_hash'] = hash('sha256', json_encode($this->canonicalise($payload), JSON_THROW_ON_ERROR));
        return RentalLedgerEntry::query()->create($payload);
    }

    private function reference(string $configKey, string $default): string
    {
        $prefix = strtoupper(trim((string) config('chatbot-ecommerce.rental_hire.' . $configKey, $default)));
        return $prefix . '-' . strtoupper((string) Str::ulid());
    }

    private function requiredKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid Idempotency-Key is required.']);
        }
        return $key;
    }

    private function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalise($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalise($item);
        }
        return $value;
    }
}
