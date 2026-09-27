<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Contracts\ShippingProvider;
use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\CheckoutSession;
use App\Extensions\ChatbotEcommerce\System\Models\ShippingQuote;
use App\Extensions\ChatbotEcommerce\System\Support\CheckoutAddress;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ShippingRuntime
{
    public function __construct(private readonly ShippingProvider $provider) {}

    /** @return list<ShippingQuote> */
    public function quote(ChatbotCart $cart, array $address, array $context = []): array
    {
        $normalised = CheckoutAddress::normalise($address);
        $addressHash = hash('sha256', json_encode($normalised, JSON_THROW_ON_ERROR));
        $checkoutId = isset($context['checkout_session_id']) ? (int) $context['checkout_session_id'] : null;
        $ttl = min(max((int) config('chatbot-ecommerce.shipping.quote_ttl_minutes', 15), 1), 120);
        $definitions = $this->provider->quotes($cart, $normalised, $context);
        $quotes = [];

        foreach ($definitions as $definition) {
            $key = hash('sha256', implode('|', [
                (string) $cart->id,
                (string) $cart->version,
                (string) $cart->pricing_snapshot_hash,
                $addressHash,
                (string) ($definition['code'] ?? ''),
                (string) ($definition['amount'] ?? 0),
            ]));
            $quote = ShippingQuote::query()->firstOrNew(['quote_key' => $key]);
            $quote->uuid ??= (string) Str::uuid();
            $quote->forceFill([
                'checkout_session_id' => $checkoutId,
                'cart_id' => $cart->id,
                'shipping_method_id' => $definition['shipping_method_id'] ?? null,
                'status' => 'active',
                'code' => (string) $definition['code'],
                'method_type' => (string) $definition['method_type'],
                'name' => (string) $definition['name'],
                'currency' => strtoupper((string) ($definition['currency'] ?? $cart->currency)),
                'amount' => max(0, (int) $definition['amount']),
                'address_hash' => $addressHash,
                'cart_version' => (int) $cart->version,
                'pricing_snapshot_hash' => $cart->pricing_snapshot_hash,
                'subtotal' => (int) ($definition['subtotal'] ?? $cart->subtotal),
                'weight_grams' => (int) ($definition['weight_grams'] ?? 0),
                'expires_at' => now()->addMinutes($ttl),
                'expired_at' => null,
                'metadata' => array_replace_recursive((array) ($definition['metadata'] ?? []), [
                    'source' => $definition['source'] ?? 'internal',
                    'estimated_days_min' => $definition['estimated_days_min'] ?? null,
                    'estimated_days_max' => $definition['estimated_days_max'] ?? null,
                ]),
            ])->save();
            $quotes[] = $quote;
        }

        Metrics::increment('shipping.quoted', ['count' => count($quotes)]);
        event(new ExtensionEvent('shipping.quoted', ['cart_uuid' => $cart->uuid, 'quote_count' => count($quotes)]));

        return $quotes;
    }

    /** @return list<ShippingQuote> */
    public function quoteForCheckout(CheckoutSession $checkout, ?ChatbotCart $cart = null): array
    {
        $cart ??= ChatbotCart::query()->findOrFail($checkout->cart_id);
        $address = (array) ($checkout->shipping_address ?? []);
        if (($checkout->delivery_method === 'delivery' || $this->requiresDelivery($cart)) && ! CheckoutAddress::isComplete($address)) {
            throw ValidationException::withMessages(['shipping_address' => 'A complete shipping address is required to calculate delivery options.']);
        }

        return $this->quote($cart, $address, ['checkout_session_id' => $checkout->id]);
    }

    public function selectQuote(ShippingQuote $quote, CheckoutSession $checkout, ChatbotCart $cart): ShippingQuote
    {
        if ((int) $quote->cart_id !== (int) $cart->id || ($quote->checkout_session_id !== null && (int) $quote->checkout_session_id !== (int) $checkout->id)) {
            throw ValidationException::withMessages(['shipping_quote' => 'The shipping quote does not belong to this checkout.']);
        }
        if ((string) $quote->status === 'expired' || ($quote->expires_at?->isPast() ?? false)) {
            throw ValidationException::withMessages(['shipping_quote' => 'The shipping quote has expired.']);
        }

        $quote->forceFill([
            'checkout_session_id' => $checkout->id,
            'status' => 'selected',
            'selected_at' => now(),
            'cart_version' => (int) $cart->version,
            'pricing_snapshot_hash' => $cart->pricing_snapshot_hash,
        ])->save();

        ShippingQuote::query()
            ->where('checkout_session_id', $checkout->id)
            ->where('id', '<>', $quote->id)
            ->where('status', 'selected')
            ->update(['status' => 'active', 'selected_at' => null]);

        return $quote->refresh();
    }

    public function refreshSelectedQuote(CheckoutSession $checkout, ChatbotCart $cart): ?ShippingQuote
    {
        if ($checkout->shipping_quote_id === null) {
            return null;
        }
        $quote = ShippingQuote::query()->whereKey($checkout->shipping_quote_id)->lockForUpdate()->first();
        if (! $quote || (string) $quote->status !== 'selected') {
            throw ValidationException::withMessages(['shipping_quote' => 'The selected shipping quote is no longer available.']);
        }

        $address = CheckoutAddress::normalise((array) $checkout->shipping_address);
        $definitions = $this->provider->quotes($cart, $address, ['checkout_session_id' => $checkout->id]);
        $current = collect($definitions)->first(fn (array $definition): bool =>
            (string) ($definition['code'] ?? '') === (string) $quote->code
            && (string) ($definition['method_type'] ?? '') === (string) $quote->method_type
        );
        if (! $current || (int) ($current['amount'] ?? -1) !== (int) $quote->amount) {
            throw ValidationException::withMessages([
                'shipping_quote' => 'The selected shipping rate changed after cart recalculation. Select a current shipping option and prepare checkout again.',
            ]);
        }

        $quote->forceFill([
            'cart_version' => (int) $cart->version,
            'pricing_snapshot_hash' => $cart->pricing_snapshot_hash,
            'subtotal' => (int) $cart->subtotal,
            'weight_grams' => (int) ($current['weight_grams'] ?? $quote->weight_grams),
            'expires_at' => now()->addMinutes(min(max((int) config('chatbot-ecommerce.checkout.approval_ttl_minutes', 15), 1), 120)),
            'metadata' => array_replace_recursive((array) ($quote->metadata ?? []), [
                'estimated_days_min' => $current['estimated_days_min'] ?? null,
                'estimated_days_max' => $current['estimated_days_max'] ?? null,
            ]),
        ])->save();

        return $quote->refresh();
    }

    public function assertQuoteStillValid(CheckoutSession $checkout, ?ChatbotCart $cart = null): ShippingQuote
    {
        $quote = $checkout->shipping_quote_id ? ShippingQuote::query()->find($checkout->shipping_quote_id) : null;
        if (! $quote) {
            throw ValidationException::withMessages(['shipping_quote' => 'A server-generated shipping quote must be selected before checkout preparation.']);
        }
        $cart ??= ChatbotCart::query()->findOrFail($checkout->cart_id);
        if ((string) $quote->status !== 'selected' || ($quote->expires_at?->isPast() ?? true)) {
            throw ValidationException::withMessages(['shipping_quote' => 'The selected shipping quote has expired.']);
        }
        if ((int) $quote->cart_id !== (int) $cart->id || (int) $quote->cart_version !== (int) $cart->version) {
            throw ValidationException::withMessages(['shipping_quote' => 'The cart changed after the shipping quote was selected.']);
        }
        if (! hash_equals((string) $quote->pricing_snapshot_hash, (string) $cart->pricing_snapshot_hash)) {
            throw ValidationException::withMessages(['shipping_quote' => 'Pricing changed after the shipping quote was selected.']);
        }
        $addressHash = hash('sha256', json_encode(CheckoutAddress::normalise((array) $checkout->shipping_address), JSON_THROW_ON_ERROR));
        if ((string) $quote->method_type === 'delivery' && ! hash_equals((string) $quote->address_hash, $addressHash)) {
            throw ValidationException::withMessages(['shipping_quote' => 'The shipping address changed after the quote was selected.']);
        }

        return $quote;
    }

    public function expireQuotes(int $limit = 500): int
    {
        return DB::transaction(function () use ($limit): int {
            $ids = ShippingQuote::query()
                ->whereIn('status', ['active', 'selected'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->limit(min(max($limit, 1), 5000))
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            $updated = ShippingQuote::query()->whereIn('id', $ids)->update(['status' => 'expired', 'expired_at' => now()]);
            Metrics::increment('shipping.quotes_expired', ['count' => $updated]);
            return $updated;
        });
    }

    /** @return array<string, mixed> */
    public function payload(ShippingQuote $quote): array
    {
        return [
            'uuid' => $quote->uuid,
            'code' => $quote->code,
            'method' => $quote->method_type,
            'name' => $quote->name,
            'currency' => $quote->currency,
            'amount' => (int) $quote->amount,
            'estimated_days_min' => $quote->metadata['estimated_days_min'] ?? null,
            'estimated_days_max' => $quote->metadata['estimated_days_max'] ?? null,
            'source' => $quote->metadata['source'] ?? 'internal',
            'expires_at' => $quote->expires_at?->toIso8601String(),
        ];
    }

    public function requiresDelivery(ChatbotCart $cart): bool
    {
        if (method_exists($this->provider, 'requiresDelivery')) {
            return (bool) $this->provider->requiresDelivery($cart);
        }
        $cart->loadMissing('cartLines.variant.product');
        foreach ($cart->cartLines as $line) {
            $type = strtolower((string) ($line->variant?->product?->product_type ?? 'physical'));
            if (! in_array($type, ['digital', 'service'], true)) {
                return true;
            }
        }
        return false;
    }
}
