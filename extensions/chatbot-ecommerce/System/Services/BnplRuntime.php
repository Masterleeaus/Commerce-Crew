<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\BnplOffer;
use App\Extensions\ChatbotEcommerce\System\Models\BnplProviderProfile;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\RentalAccount;
use App\Extensions\ChatbotEcommerce\System\Models\RentalPayment;
use App\Extensions\ChatbotEcommerce\System\Support\BnplEligibility;
use App\Extensions\ChatbotEcommerce\System\Support\BnplInstallmentSchedule;
use App\Extensions\ChatbotEcommerce\System\Support\BnplStateToken;
use App\Extensions\ChatbotEcommerce\System\Support\BnplStatus;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutStatus;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use App\Extensions\ChatbotEcommerce\System\Support\ShoppingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BnplRuntime
{
    public function __construct(private readonly PaymentRuntime $payments) {}

    /** @return list<array<string,mixed>> */
    public function offersForCheckout(CheckoutSession $checkout, array $context = []): array
    {
        if (! in_array((string) $checkout->status, [CheckoutStatus::READY, CheckoutStatus::PAYMENT_PENDING], true)) {
            throw ValidationException::withMessages(['checkout' => 'BNPL offers are available only after checkout preparation.']);
        }

        return $this->offerDefinitions(
            (int) $checkout->chatbot_id,
            (int) $checkout->total,
            (string) $checkout->currency,
            'checkout',
            null,
            $context,
        );
    }

    public function selectForCheckout(
        CheckoutSession $checkout,
        string $providerCode,
        int $installmentCount,
        string $idempotencyKey,
        array $context = [],
    ): BnplOffer {
        if ((string) $checkout->status !== CheckoutStatus::PAYMENT_PENDING) {
            throw ValidationException::withMessages(['checkout' => 'The checkout must be approved and payment pending before a BNPL offer can be selected.']);
        }

        return $this->select(
            profile: $this->profile($providerCode, (int) $checkout->chatbot_id),
            amount: (int) $checkout->total,
            currency: (string) $checkout->currency,
            scope: 'checkout',
            shoppingMode: ShoppingMode::normalize($context['shopping_mode'] ?? null),
            accountType: null,
            installmentCount: $installmentCount,
            idempotencyKey: $idempotencyKey,
            context: $context,
            checkout: $checkout,
        );
    }

    /** @return list<array<string,mixed>> */
    public function offersForRentalPayment(RentalPayment $payment, RentalAccount $account, array $context = []): array
    {
        $this->assertRentalPayment($payment, $account);
        $this->assertRentalAccountTypeEnabled((string) $account->account_type);

        return $this->offerDefinitions(
            $account->chatbot_id !== null ? (int) $account->chatbot_id : null,
            (int) $payment->amount,
            (string) $payment->currency,
            'rental_hire',
            (string) $account->account_type,
            $context,
        );
    }

    public function selectForRentalPayment(
        RentalPayment $payment,
        RentalAccount $account,
        string $providerCode,
        int $installmentCount,
        string $idempotencyKey,
        array $context = [],
    ): BnplOffer {
        $this->assertRentalPayment($payment, $account);
        $this->assertRentalAccountTypeEnabled((string) $account->account_type);

        return $this->select(
            profile: $this->profile($providerCode, $account->chatbot_id !== null ? (int) $account->chatbot_id : null),
            amount: (int) $payment->amount,
            currency: (string) $payment->currency,
            scope: 'rental_hire',
            shoppingMode: ShoppingMode::NATIVE,
            accountType: (string) $account->account_type,
            installmentCount: $installmentCount,
            idempotencyKey: $idempotencyKey,
            context: $context,
            rentalPayment: $payment,
            rentalAccount: $account,
        );
    }

    public function expireDue(int $limit = 500): int
    {
        $ids = BnplOffer::query()
            ->whereIn('status', [BnplStatus::QUOTED, BnplStatus::REQUIRES_ACTION, BnplStatus::APPROVED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            $changed = BnplOffer::query()
                ->whereKey($id)
                ->whereIn('status', [BnplStatus::QUOTED, BnplStatus::REQUIRES_ACTION, BnplStatus::APPROVED])
                ->update(['status' => BnplStatus::EXPIRED, 'updated_at' => now()]);
            $expired += $changed;
        }

        if ($expired > 0) {
            Metrics::increment('bnpl.offers.expired', ['count' => $expired]);
        }

        return $expired;
    }

    /** @return list<array<string,mixed>> */
    private function offerDefinitions(
        ?int $chatbotId,
        int $amount,
        string $currency,
        string $scope,
        ?string $accountType,
        array $context,
    ): array {
        $shoppingMode = ShoppingMode::normalize($context['shopping_mode'] ?? null);
        $country = isset($context['country']) ? strtoupper(trim((string) $context['country'])) : null;
        $definitions = [];

        foreach ($this->profiles($chatbotId) as $profile) {
            $eligibility = BnplEligibility::evaluate(
                $profile->policy(),
                $amount,
                $currency,
                $country,
                $scope,
                $shoppingMode,
                $accountType,
                (bool) config('chatbot-ecommerce.bnpl.require_licensed_provider', true),
            );

            if (! $eligibility['eligible'] && ! $eligibility['display_only']) {
                continue;
            }

            $plans = [];
            foreach ($this->installmentCounts($profile) as $count) {
                $schedule = BnplInstallmentSchedule::build(
                    $amount,
                    $count,
                    max(1, (int) $profile->interval_days),
                    now()->addDays(max(0, (int) $profile->first_payment_delay_days))->toDateString(),
                );
                $plans[] = [
                    'installment_count' => $count,
                    'interval_days' => (int) $profile->interval_days,
                    'first_installment_amount' => (int) $schedule[0]['amount'],
                    'regular_installment_amount' => (int) ($schedule[1]['amount'] ?? $schedule[0]['amount']),
                    'schedule' => $schedule,
                ];
            }

            $definitions[] = [
                'provider_code' => $profile->code,
                'provider_name' => $profile->display_name,
                'provider_type' => $profile->provider_type,
                'shopping_mode' => $shoppingMode,
                'availability' => $eligibility['availability'],
                'display_only' => $eligibility['display_only'],
                'reason' => $eligibility['reason'],
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'plans' => $plans,
                'disclosures' => [
                    'licence_reference' => $profile->licence_reference,
                    'terms_url' => $profile->terms_url,
                    'privacy_url' => $profile->privacy_url,
                    'hardship_url' => $profile->hardship_url,
                    'complaints_url' => $profile->complaints_url,
                    'credit_decision' => 'The BNPL provider makes the final eligibility and credit decision.',
                ],
            ];
        }

        return $definitions;
    }

    private function select(
        BnplProviderProfile $profile,
        int $amount,
        string $currency,
        string $scope,
        string $shoppingMode,
        ?string $accountType,
        int $installmentCount,
        string $idempotencyKey,
        array $context,
        ?CheckoutSession $checkout = null,
        ?RentalPayment $rentalPayment = null,
        ?RentalAccount $rentalAccount = null,
    ): BnplOffer {
        if ($shoppingMode !== ShoppingMode::NATIVE) {
            throw ValidationException::withMessages(['shopping_mode' => 'Marketplace-assisted BNPL must be completed in the marketplace checkout.']);
        }
        if (! (bool) ($context['customer_accepts_provider_terms'] ?? false)) {
            throw ValidationException::withMessages(['customer_accepts_provider_terms' => 'The customer must accept the BNPL provider terms before continuing.']);
        }

        $eligibility = BnplEligibility::evaluate(
            $profile->policy(),
            $amount,
            $currency,
            isset($context['country']) ? (string) $context['country'] : null,
            $scope,
            $shoppingMode,
            $accountType,
            (bool) config('chatbot-ecommerce.bnpl.require_licensed_provider', true),
        );
        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages(['bnpl' => 'The selected BNPL provider is unavailable: ' . ($eligibility['reason'] ?? 'ineligible') . '.']);
        }
        if (! in_array($installmentCount, $this->installmentCounts($profile), true)) {
            throw ValidationException::withMessages(['installment_count' => 'The selected installment count is not offered by this provider.']);
        }
        $this->assertProfileCheckoutReady($profile);

        $key = $this->requiredKey($idempotencyKey);
        $requestHash = $this->requestHash([
            $profile->id,
            $amount,
            strtoupper($currency),
            $scope,
            $shoppingMode,
            $accountType,
            $installmentCount,
            $checkout?->id,
            $rentalPayment?->id,
        ]);

        $offer = BnplOffer::query()->where('idempotency_key', $key)->first();
        if ($offer) {
            if (! hash_equals((string) $offer->request_hash, $requestHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for another BNPL offer.']);
            }
            if ($offer->payment_intent_id !== null) {
                return $offer->load(['providerProfile', 'paymentIntent']);
            }
        }

        $firstDueOn = now()->addDays(max(0, (int) $profile->first_payment_delay_days))->toDateString();
        $schedule = BnplInstallmentSchedule::build($amount, $installmentCount, max(1, (int) $profile->interval_days), $firstDueOn);
        $merchantFee = intdiv($amount * max(0, (int) $profile->merchant_fee_bps) + 9999, 10000);
        $expiresAt = now()->addMinutes($this->offerTtlMinutes());

        $offer ??= DB::transaction(function () use (
            $profile,
            $amount,
            $currency,
            $scope,
            $shoppingMode,
            $installmentCount,
            $schedule,
            $merchantFee,
            $eligibility,
            $key,
            $requestHash,
            $expiresAt,
            $context,
            $checkout,
            $rentalPayment,
            $rentalAccount,
        ): BnplOffer {
            return BnplOffer::query()->create([
                'bnpl_provider_profile_id' => $profile->id,
                'checkout_session_id' => $checkout?->id,
                'rental_payment_id' => $rentalPayment?->id,
                'chatbot_id' => $checkout?->chatbot_id ?? $rentalAccount?->chatbot_id,
                'customer_identity_id' => $checkout?->customer_identity_id ?? $rentalAccount?->customer_identity_id,
                'scope' => $scope,
                'shopping_mode' => $shoppingMode,
                'status' => BnplStatus::QUOTED,
                'amount' => $amount,
                'customer_fee' => 0,
                'merchant_fee' => $merchantFee,
                'total_payable' => $amount,
                'currency' => strtoupper($currency),
                'installment_count' => $installmentCount,
                'interval_days' => (int) $profile->interval_days,
                'first_installment_amount' => (int) $schedule[0]['amount'],
                'regular_installment_amount' => (int) ($schedule[1]['amount'] ?? $schedule[0]['amount']),
                'installment_schedule' => $schedule,
                'eligibility_snapshot' => array_replace($eligibility, [
                    'country' => isset($context['country']) ? strtoupper((string) $context['country']) : null,
                    'account_type' => $rentalAccount?->account_type,
                    'provider_licence_reference' => $profile->licence_reference,
                ]),
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'metadata' => $context['metadata'] ?? [],
                'expires_at' => $expiresAt,
                'selected_at' => now(),
            ]);
        });

        $approvalUrl = $this->approvalUrl($offer, $profile, $context);
        $paymentAttributes = [
            'payment_url' => $approvalUrl,
            'instructions' => [
                'type' => 'bnpl',
                'provider_code' => $profile->code,
                'provider_name' => $profile->display_name,
                'installment_count' => $installmentCount,
                'installment_schedule' => $schedule,
                'terms_url' => $profile->terms_url,
                'hardship_url' => $profile->hardship_url,
                'complaints_url' => $profile->complaints_url,
            ],
            'metadata' => array_replace((array) ($context['metadata'] ?? []), [
                'bnpl_offer_uuid' => $offer->uuid,
                'bnpl_provider_code' => $profile->code,
                'shopping_mode' => $shoppingMode,
            ]),
        ];

        $intent = $checkout
            ? $this->payments->createForCheckout($checkout, 'bnpl', 'bnpl-payment:' . $key, $paymentAttributes)
            : $this->payments->createForRentalPayment($rentalPayment, 'bnpl', 'bnpl-payment:' . $key, $paymentAttributes);

        $offer->forceFill([
            'payment_intent_id' => $intent->id,
            'status' => BnplStatus::REQUIRES_ACTION,
            'provider_reference' => $intent->provider_payment_id,
            'approval_url' => $approvalUrl,
        ])->save();

        Metrics::increment('bnpl.offer.selected', ['scope' => $scope, 'provider' => $profile->code]);
        event(new ExtensionEvent('bnpl.offer.selected', [
            'offer_uuid' => $offer->uuid,
            'payment_intent_uuid' => $intent->uuid,
            'provider_code' => $profile->code,
            'scope' => $scope,
        ]));

        return $offer->fresh(['providerProfile', 'paymentIntent']);
    }

    /** @return Collection<int,BnplProviderProfile> */
    private function profiles(?int $chatbotId): Collection
    {
        $query = BnplProviderProfile::query()->where('active', true);
        $query->where(function ($query) use ($chatbotId): void {
            $query->whereNull('chatbot_id');
            if ($chatbotId !== null) {
                $query->orWhere('chatbot_id', $chatbotId);
            }
        });

        $profiles = $query->orderBy('id')->get();
        $resolved = [];
        foreach ($profiles as $profile) {
            $code = strtolower((string) $profile->code);
            if (! isset($resolved[$code]) || $profile->chatbot_id !== null) {
                $resolved[$code] = $profile;
            }
        }

        return new Collection(array_values($resolved));
    }

    private function profile(string $code, ?int $chatbotId): BnplProviderProfile
    {
        $code = strtolower(trim($code));
        $profile = $this->profiles($chatbotId)->first(fn (BnplProviderProfile $profile): bool => strtolower((string) $profile->code) === $code);
        if (! $profile) {
            throw ValidationException::withMessages(['provider_code' => 'The selected BNPL provider profile is unavailable.']);
        }

        return $profile;
    }

    /** @return list<int> */
    private function installmentCounts(BnplProviderProfile $profile): array
    {
        $maximum = min(max((int) config('chatbot-ecommerce.bnpl.maximum_installments', 24), 2), 60);
        $counts = array_values(array_unique(array_map('intval', (array) ($profile->installment_counts ?? []))));
        $counts = array_values(array_filter($counts, static fn (int $count): bool => $count >= 2 && $count <= $maximum));
        sort($counts);

        return $counts;
    }

    private function assertProfileCheckoutReady(BnplProviderProfile $profile): void
    {
        $base = trim((string) $profile->hosted_checkout_url);
        if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages(['provider' => 'The BNPL provider does not have a valid hosted checkout URL.']);
        }
        $secret = trim((string) config('chatbot-ecommerce.bnpl.providers.' . $profile->code . '.state_secret', config('chatbot-ecommerce.bnpl.state_secret', '')));
        if ($secret === '') {
            throw ValidationException::withMessages(['provider' => 'The BNPL provider state secret is not configured.']);
        }
    }

    private function approvalUrl(BnplOffer $offer, BnplProviderProfile $profile, array $context): string
    {
        $base = trim((string) $profile->hosted_checkout_url);
        if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages(['provider' => 'The BNPL provider does not have a valid hosted checkout URL.']);
        }

        $secret = trim((string) config('chatbot-ecommerce.bnpl.providers.' . $profile->code . '.state_secret', config('chatbot-ecommerce.bnpl.state_secret', '')));
        if ($secret === '') {
            throw ValidationException::withMessages(['provider' => 'The BNPL provider state secret is not configured.']);
        }

        $state = BnplStateToken::issue([
            'offer_uuid' => $offer->uuid,
            'provider_code' => $profile->code,
            'amount' => (int) $offer->amount,
            'currency' => $offer->currency,
            'installment_count' => (int) $offer->installment_count,
        ], $secret, time(), $this->offerTtlMinutes() * 60);

        $query = [
            'offer' => $offer->uuid,
            'state' => $state,
            'amount' => (int) $offer->amount,
            'currency' => $offer->currency,
            'installments' => (int) $offer->installment_count,
        ];
        if (! empty($context['return_url']) && filter_var($context['return_url'], FILTER_VALIDATE_URL)) {
            $query['return_url'] = (string) $context['return_url'];
        }

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function assertRentalPayment(RentalPayment $payment, RentalAccount $account): void
    {
        if ((int) $payment->rental_account_id !== (int) $account->id) {
            throw ValidationException::withMessages(['payment' => 'The payment does not belong to this rental or hire account.']);
        }
        if ((string) $payment->status !== 'pending') {
            throw ValidationException::withMessages(['payment' => 'Only pending rental or hire payment requests can use BNPL.']);
        }
        if ($payment->expires_at?->isPast()) {
            throw ValidationException::withMessages(['payment' => 'The rental or hire payment request has expired.']);
        }
    }

    private function assertRentalAccountTypeEnabled(string $accountType): void
    {
        $accountType = strtolower(trim($accountType));
        $key = match ($accountType) {
            'rent' => 'rent_enabled',
            'hire' => 'hire_enabled',
            'lease' => 'lease_enabled',
            default => 'other_enabled',
        };
        if (! (bool) config('chatbot-ecommerce.bnpl.rental_hire.' . $key, false)) {
            throw ValidationException::withMessages(['account_type' => 'BNPL is disabled for this rental or hire account type.']);
        }
    }

    private function requiredKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'The Idempotency-Key header is required.']);
        }

        return substr($key, 0, 191);
    }

    private function requestHash(array $data): string
    {
        return hash('sha256', json_encode($this->canonicalise($data), JSON_THROW_ON_ERROR));
    }

    private function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalise($item);
        }

        return $value;
    }

    private function offerTtlMinutes(): int
    {
        return min(max((int) config('chatbot-ecommerce.bnpl.offer_ttl_minutes', 15), 5), 1440);
    }
}
