<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\Coupon;
use App\Extensions\ChatbotEcommerce\System\Models\CouponUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CouponRuntime
{
    public const RESERVED = 'reserved';
    public const REDEEMED = 'redeemed';
    public const RELEASED = 'released';
    public const EXPIRED = 'expired';

    /**
     * @param list<array<string, mixed>> $lines
     */
    public function resolve(
        ?string $code,
        int $subtotal,
        array $lines,
        array $context = [],
        bool $strict = false,
    ): ?Coupon {
        $normalisedCode = strtoupper(trim((string) $code));
        if ($normalisedCode === '') {
            return null;
        }

        $chatbotId = (int) ($context['chatbot_id'] ?? 0);
        if ($chatbotId <= 0) {
            if ($strict) { throw ValidationException::withMessages(['chatbot_id' => 'A tenant chatbot context is required to validate coupons.']); }
            return null;
        }
        $coupon = Coupon::query()
            ->where('chatbot_id', $chatbotId)
            ->whereRaw('UPPER(code) = ?', [$normalisedCode])
            ->where('active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->first();

        $error = $this->validationError($coupon, $subtotal, $lines, $context);
        if ($error !== null) {
            if ($strict) {
                throw ValidationException::withMessages(['code' => $error]);
            }

            return null;
        }

        return $coupon;
    }

    public function reserve(Coupon $coupon, array $context = []): void
    {
        $cartId = (int) ($context['cart_id'] ?? 0);
        if ($cartId <= 0 || ! Schema::hasTable('ext_chatbot_coupon_usages')) {
            return;
        }

        DB::transaction(function () use ($coupon, $context, $cartId): void {
            Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $this->assertUsageAvailable($coupon, $context, $cartId);

            CouponUsage::query()
                ->where('cart_id', $cartId)
                ->where('coupon_id', '!=', $coupon->id)
                ->where('status', self::RESERVED)
                ->update([
                    'status' => self::RELEASED,
                    'released_at' => now(),
                    'expires_at' => null,
                    'updated_at' => now(),
                ]);

            $existing = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('cart_id', $cartId)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === self::REDEEMED) {
                return;
            }

            CouponUsage::query()->updateOrCreate(
                ['coupon_id' => $coupon->id, 'cart_id' => $cartId],
                [
                    'uuid' => $existing?->uuid ?: (string) Str::uuid(),
                    'customer_identity_id' => $context['customer_identity_id'] ?? null,
                    'status' => self::RESERVED,
                    'reserved_at' => $existing?->reserved_at ?: now(),
                    'redeemed_at' => null,
                    'released_at' => null,
                    'expires_at' => now()->addMinutes((int) config('chatbot-ecommerce.pricing.coupon_reservation_ttl_minutes', 30)),
                    'metadata' => [
                        'channel' => $context['channel'] ?? null,
                        'currency' => $context['currency'] ?? null,
                    ],
                ],
            );
        });
    }

    public function releaseForCart(int $cartId): int
    {
        if ($cartId <= 0 || ! Schema::hasTable('ext_chatbot_coupon_usages')) {
            return 0;
        }

        return CouponUsage::query()
            ->where('cart_id', $cartId)
            ->where('status', self::RESERVED)
            ->update([
                'status' => self::RELEASED,
                'released_at' => now(),
                'expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function redeemForCart(int $cartId): int
    {
        if ($cartId <= 0 || ! Schema::hasTable('ext_chatbot_coupon_usages')) {
            return 0;
        }

        return CouponUsage::query()
            ->where('cart_id', $cartId)
            ->where('status', self::RESERVED)
            ->update([
                'status' => self::REDEEMED,
                'redeemed_at' => now(),
                'expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function expireDue(int $limit = 500): int
    {
        if (! Schema::hasTable('ext_chatbot_coupon_usages')) {
            return 0;
        }

        $ids = CouponUsage::query()
            ->where('status', self::RESERVED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->pluck('id');

        return CouponUsage::query()
            ->whereIn('id', $ids)
            ->where('status', self::RESERVED)
            ->update([
                'status' => self::EXPIRED,
                'released_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    public function eligibleLineKeys(Coupon $coupon, array $lines): array
    {
        $appliesTo = (string) ($coupon->applies_to ?: 'cart');
        $targets = array_map('intval', $coupon->target_ids ?? []);
        $keys = [];

        foreach ($lines as $line) {
            $eligible = match ($appliesTo) {
                'products' => in_array((int) ($line['product_id'] ?? 0), $targets, true),
                'variants' => in_array((int) ($line['variant_id'] ?? 0), $targets, true),
                'categories' => in_array((int) ($line['category_id'] ?? 0), $targets, true),
                default => true,
            };

            if ($eligible) {
                $keys[] = (string) ($line['line_key'] ?? '');
            }
        }

        return array_values(array_filter($keys, static fn (string $key): bool => $key !== ''));
    }

    /** @param list<array<string, mixed>> $lines */
    private function validationError(?Coupon $coupon, int $subtotal, array $lines, array $context): ?string
    {
        if (! $coupon) {
            return 'The coupon is invalid or unavailable.';
        }
        if ($subtotal < (int) $coupon->minimum_amount) {
            return 'The coupon minimum purchase has not been met.';
        }
        if ($coupon->currency && strtoupper((string) ($context['currency'] ?? '')) !== strtoupper((string) $coupon->currency)) {
            return 'The coupon is not valid for this currency.';
        }
        $channels = array_values(array_filter($coupon->channels ?? []));
        if ($channels !== [] && ! in_array((string) ($context['channel'] ?? ''), $channels, true)) {
            return 'The coupon is not valid on this channel.';
        }
        if ($coupon->first_order_only && (int) ($context['completed_order_count'] ?? 0) > 0) {
            return 'The coupon is limited to a customer’s first order.';
        }
        if ($this->eligibleLineKeys($coupon, $lines) === []) {
            return 'The coupon does not apply to the products in this cart.';
        }

        try {
            $this->assertUsageAvailable($coupon, $context, (int) ($context['cart_id'] ?? 0));
        } catch (ValidationException $exception) {
            return (string) collect($exception->errors())->flatten()->first();
        }

        return null;
    }

    private function assertUsageAvailable(Coupon $coupon, array $context, int $currentCartId): void
    {
        if (! Schema::hasTable('ext_chatbot_coupon_usages')) {
            return;
        }

        $active = CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->where(function (Builder $query): void {
                $query->where('status', self::REDEEMED)
                    ->orWhere(function (Builder $reserved): void {
                        $reserved->where('status', self::RESERVED)
                            ->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                    });
            })
            ->when($currentCartId > 0, fn (Builder $query) => $query->where('cart_id', '!=', $currentCartId));

        if ($coupon->usage_limit !== null && (clone $active)->count() >= (int) $coupon->usage_limit) {
            throw ValidationException::withMessages(['code' => 'The coupon usage limit has been reached.']);
        }

        $customerId = (int) ($context['customer_identity_id'] ?? 0);
        if ($customerId > 0 && $coupon->usage_limit_per_customer !== null) {
            $customerUses = (clone $active)->where('customer_identity_id', $customerId)->count();
            if ($customerUses >= (int) $coupon->usage_limit_per_customer) {
                throw ValidationException::withMessages(['code' => 'The coupon usage limit for this customer has been reached.']);
            }
        }
    }
}
