<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\CartOperation;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCartLine;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryReservation;
use App\Extensions\ChatbotEcommerce\System\Models\PricingSnapshot;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;
use App\Extensions\ChatbotEcommerce\System\Support\CartLineKey;
use App\Extensions\ChatbotEcommerce\System\Support\CartLineQuantity;
use App\Extensions\ChatbotEcommerce\System\Support\CartStatus;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryReservationState;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CartRuntime
{
    public function __construct(
        private readonly PricingRuntime $pricing,
        private readonly InventoryRuntime $inventory,
    ) {}

    public function getOrCreate(
        int $chatbotId,
        string $sessionId,
        ?int $customerId = null,
        array $context = [],
    ): ChatbotCart {
        $activeKey = $this->activeKey($chatbotId, $sessionId);

        try {
            return DB::transaction(function () use ($chatbotId, $sessionId, $customerId, $context, $activeKey): ChatbotCart {
                $cart = ChatbotCart::query()->where('active_key', $activeKey)->lockForUpdate()->first();

                if (! $cart) {
                    $cart = ChatbotCart::query()
                        ->where('chatbot_id', $chatbotId)
                        ->where('session_id', $sessionId)
                        ->where('product_source', 'internal')
                        ->where('status', CartStatus::ACTIVE)
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();
                }

                if ($cart && ! ($cart->expires_at?->isPast() ?? false)) {
                    $cart->forceFill([
                        'uuid' => $cart->uuid ?: (string) Str::uuid(),
                        'active_key' => $activeKey,
                        'chatbot_customer_id' => $customerId ?? $cart->chatbot_customer_id,
                        'customer_identity_id' => $context['customer_identity_id'] ?? $cart->customer_identity_id,
                        'conversation_id' => $context['conversation_id'] ?? $cart->conversation_id,
                        'channel' => $context['channel'] ?? $cart->channel,
                        'last_activity_at' => now(),
                    ])->save();

                    return $this->hydrate($cart);
                }

                if ($cart) {
                    $this->expireLockedCart($cart);
                }

                $created = ChatbotCart::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'chatbot_customer_id' => $customerId ?? 0,
                    'chatbot_id' => $chatbotId,
                    'session_id' => $sessionId,
                    'product_source' => 'internal',
                    'products' => [],
                    'product_data' => [],
                    'status' => CartStatus::ACTIVE,
                    'currency' => 'USD',
                    'lines' => [],
                    'active_key' => $activeKey,
                    'customer_identity_id' => $context['customer_identity_id'] ?? null,
                    'conversation_id' => $context['conversation_id'] ?? null,
                    'channel' => $context['channel'] ?? null,
                    'expires_at' => now()->addDays(7),
                    'last_activity_at' => now(),
                    'metadata' => $context['metadata'] ?? [],
                ]);

                Metrics::increment('cart.created');
                event(new ExtensionEvent('cart.created', ['cart_uuid' => $created->uuid, 'chatbot_id' => $chatbotId]));

                return $this->hydrate($created);
            });
        } catch (QueryException $exception) {
            $existing = ChatbotCart::query()->where('active_key', $activeKey)->first();
            if ($existing) {
                return $this->hydrate($existing);
            }

            throw $exception;
        }
    }

    /** Backward-compatible setter retained from v3.0. */
    public function putLine(ChatbotCart $cart, int $variantId, int $quantity): ChatbotCart
    {
        return $this->setLine($cart, $variantId, $quantity);
    }

    public function addLine(
        ChatbotCart $cart,
        int $variantId,
        int $quantity = 1,
        array $customisation = [],
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): ChatbotCart {
        $quantity = min(max($quantity, 1), 999);
        $payload = compact('variantId', 'quantity', 'customisation', 'metadata');

        return $this->operate($cart, $idempotencyKey, 'line.add', $payload, function (ChatbotCart $locked) use ($variantId, $quantity, $customisation, $metadata): ChatbotCart {
            $this->assertMutable($locked);
            $this->assertVariantForCart($locked, $variantId);
            $lineKey = CartLineKey::make($variantId, $customisation);
            $lines = collect($this->linePayloads($locked));
            $current = $lines->firstWhere('line_key', $lineKey);
            $next = CartLineQuantity::normalise((int) ($current['quantity'] ?? 0) + $quantity);
            $lines = $lines->reject(fn (array $line): bool => (string) ($line['line_key'] ?? '') === $lineKey)->values();
            $lines->push([
                'line_key' => $lineKey,
                'variant_id' => $variantId,
                'quantity' => $next,
                'customisation' => $customisation,
                'metadata' => array_replace_recursive($current['metadata'] ?? [], $metadata),
            ]);

            return $this->persistQuote($locked, $this->pricing->quote($lines->all(), $locked->coupon_code, false, $this->pricingContext($locked)));
        });
    }

    public function setLine(
        ChatbotCart $cart,
        int $variantId,
        int $quantity,
        array $customisation = [],
        array $metadata = [],
        ?string $idempotencyKey = null,
        string $operation = 'line.set',
    ): ChatbotCart {
        $quantity = CartLineQuantity::normalise($quantity);
        $payload = compact('variantId', 'quantity', 'customisation', 'metadata');

        return $this->operate($cart, $idempotencyKey, $operation, $payload, function (ChatbotCart $locked) use ($variantId, $quantity, $customisation, $metadata): ChatbotCart {
            $this->assertMutable($locked);
            $this->assertVariantForCart($locked, $variantId);
            $lineKey = CartLineKey::make($variantId, $customisation);
            $lines = collect($this->linePayloads($locked))
                ->reject(fn (array $line): bool => (string) ($line['line_key'] ?? '') === $lineKey)
                ->values();

            if ($quantity > 0) {
                $lines->push([
                    'line_key' => $lineKey,
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'customisation' => $customisation,
                    'metadata' => $metadata,
                ]);
            }

            $newVariantQuantity = (int) $lines->where('variant_id', $variantId)->sum('quantity');
            $this->releaseExcessReservations($locked, $variantId, $newVariantQuantity);
            return $this->persistQuote($locked, $this->pricing->quote($lines->all(), $locked->coupon_code, false, $this->pricingContext($locked)));
        });
    }

    public function updateLine(
        ChatbotCart $cart,
        ChatbotCartLine $line,
        int $quantity,
        ?string $idempotencyKey = null,
    ): ChatbotCart {
        if ((int) $line->cart_id !== (int) $cart->id) {
            throw ValidationException::withMessages(['line' => 'The cart line does not belong to this cart.']);
        }

        return $this->setLine(
            $cart,
            (int) $line->variant_id,
            $quantity,
            $line->customisation ?? [],
            $line->metadata ?? [],
            $idempotencyKey,
            'line.update',
        );
    }

    public function removeLine(ChatbotCart $cart, ChatbotCartLine $line, ?string $idempotencyKey = null): ChatbotCart
    {
        return $this->updateLine($cart, $line, 0, $idempotencyKey);
    }

    public function clear(ChatbotCart $cart, ?string $idempotencyKey = null): ChatbotCart
    {
        return $this->operate($cart, $idempotencyKey, 'cart.clear', [], function (ChatbotCart $locked): ChatbotCart {
            $this->assertMutable($locked);
            $this->releaseAllReservations($locked);
            return $this->persistQuote($locked, $this->pricing->quote([], null, false, $this->pricingContext($locked)));
        });
    }

    public function recalculate(ChatbotCart $cart, ?string $idempotencyKey = null): ChatbotCart
    {
        return $this->operate($cart, $idempotencyKey, 'cart.recalculate', [], function (ChatbotCart $locked): ChatbotCart {
            $this->assertMutable($locked);
            return $this->persistQuote($locked, $this->pricing->quote($this->linePayloads($locked), $locked->coupon_code, false, $this->pricingContext($locked)));
        });
    }

    public function applyCoupon(ChatbotCart $cart, ?string $code, ?string $idempotencyKey = null): ChatbotCart
    {
        return $this->operate($cart, $idempotencyKey, 'coupon.apply', ['code' => $code], function (ChatbotCart $locked) use ($code): ChatbotCart {
            $this->assertMutable($locked);
            return $this->persistQuote($locked, $this->pricing->quote($this->linePayloads($locked), $code, $code !== null, $this->pricingContext($locked)));
        });
    }

    public function removeCoupon(ChatbotCart $cart, ?string $idempotencyKey = null): ChatbotCart
    {
        return $this->applyCoupon($cart, null, $idempotencyKey);
    }

    public function merge(ChatbotCart $source, ChatbotCart $target, ?string $idempotencyKey = null): ChatbotCart
    {
        if ((int) $source->id === (int) $target->id) {
            return $this->hydrate($target);
        }
        if ((int) $source->chatbot_id !== (int) $target->chatbot_id) {
            throw ValidationException::withMessages(['source_session_id' => 'Carts from different chatbots cannot be merged.']);
        }
        if ($source->product_source !== 'internal' || $target->product_source !== 'internal') {
            throw ValidationException::withMessages(['cart' => 'Only native internal carts can be merged by this runtime.']);
        }

        $key = $idempotencyKey ? trim($idempotencyKey) : null;
        $requestHash = hash('sha256', json_encode([
            'cart.merge',
            'source_cart_id' => (int) $source->id,
            'target_cart_id' => (int) $target->id,
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($source, $target, $key, $requestHash): ChatbotCart {
                $ids = [(int) $source->id, (int) $target->id];
                sort($ids);
                $locked = ChatbotCart::query()
                    ->whereIn('id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $lockedSource = $locked->get((int) $source->id);
                $lockedTarget = $locked->get((int) $target->id);

                if (! $lockedSource || ! $lockedTarget) {
                    throw ValidationException::withMessages(['cart' => 'One of the carts no longer exists.']);
                }

                if ($key) {
                    $existing = CartOperation::query()
                        ->where('cart_id', $lockedTarget->id)
                        ->where('idempotency_key', $key)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                            throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for a different cart operation.']);
                        }
                        return $this->hydrate($lockedTarget);
                    }
                }

                $this->assertMutable($lockedSource);
                $this->assertMutable($lockedTarget);

                $combined = [];
                foreach (array_merge($this->linePayloads($lockedTarget), $this->linePayloads($lockedSource)) as $line) {
                    $lineKey = (string) ($line['line_key'] ?? CartLineKey::make((int) $line['variant_id'], $line['customisation'] ?? []));
                    if (! isset($combined[$lineKey])) {
                        $line['line_key'] = $lineKey;
                        $combined[$lineKey] = $line;
                    } else {
                        $combined[$lineKey]['quantity'] = CartLineQuantity::normalise(
                            (int) $combined[$lineKey]['quantity'] + (int) ($line['quantity'] ?? 0),
                        );
                    }
                }

                $coupon = $lockedTarget->coupon_code ?: $lockedSource->coupon_code;
                $this->pricing->releaseCouponReservation((int) $lockedSource->id);
                $targetResult = $this->persistQuote($lockedTarget, $this->pricing->quote(array_values($combined), $coupon, false, $this->pricingContext($lockedTarget)));

                InventoryReservation::query()
                    ->where('cart_id', $lockedSource->id)
                    ->where('status', InventoryReservationState::ACTIVE)
                    ->update(['cart_id' => $lockedTarget->id, 'updated_at' => now()]);

                $lockedSource->forceFill([
                    'status' => CartStatus::MERGED,
                    'active_key' => null,
                    'merged_into_cart_id' => $lockedTarget->id,
                    'recovery_token_hash' => null,
                    'last_activity_at' => now(),
                    'version' => (int) $lockedSource->version + 1,
                ])->save();

                if ($key) {
                    CartOperation::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'cart_id' => $lockedTarget->id,
                        'idempotency_key' => $key,
                        'request_hash' => $requestHash,
                        'operation' => 'cart.merge',
                        'response' => $this->snapshot($targetResult),
                    ]);
                }

                Metrics::increment('cart.merged');
                event(new ExtensionEvent('cart.merged', [
                    'source_cart_uuid' => $lockedSource->uuid,
                    'target_cart_uuid' => $targetResult->uuid,
                ]));

                return $this->hydrate($targetResult);
            });
        } catch (QueryException $exception) {
            if ($key) {
                $existing = CartOperation::query()
                    ->where('cart_id', $target->id)
                    ->where('idempotency_key', $key)
                    ->first();
                if ($existing && hash_equals((string) $existing->request_hash, $requestHash)) {
                    return $this->hydrate(ChatbotCart::query()->findOrFail($target->id));
                }
            }
            throw $exception;
        }
    }

    /** @return array{cart: ChatbotCart, recovery_token: string} */
    public function abandon(ChatbotCart $cart, ?string $idempotencyKey = null): array
    {
        $token = $idempotencyKey
            ? hash_hmac('sha256', (string) $cart->uuid . '|' . trim($idempotencyKey), (string) config('app.key'))
            : Str::random(64);
        $updated = $this->operate($cart, $idempotencyKey, 'cart.abandon', [], function (ChatbotCart $locked) use ($token): ChatbotCart {
            $this->transition($locked, CartStatus::ABANDONED);
            $locked->forceFill([
                'active_key' => null,
                'recovery_token_hash' => hash('sha256', $token),
                'abandoned_at' => now(),
                'expires_at' => now()->addDays(30),
                'last_activity_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();
            event(new ExtensionEvent('cart.abandoned', ['cart_uuid' => $locked->uuid]));
            return $locked;
        });

        return ['cart' => $this->hydrate($updated), 'recovery_token' => $token];
    }

    public function recover(int $chatbotId, string $sessionId, string $token): ChatbotCart
    {
        $tokenHash = hash('sha256', $token);
        $source = ChatbotCart::query()
            ->where('chatbot_id', $chatbotId)
            ->where('recovery_token_hash', $tokenHash)
            ->firstOrFail();

        if ($source->expires_at?->isPast() ?? false) {
            throw ValidationException::withMessages(['recovery_token' => 'The cart recovery token has expired.']);
        }

        $activeKey = $this->activeKey($chatbotId, $sessionId);
        $existing = ChatbotCart::query()->where('active_key', $activeKey)->first();
        if ($existing && (int) $existing->id !== (int) $source->id) {
            $merged = $this->merge($source, $existing, 'recover:' . $tokenHash);
            $source->forceFill(['recovery_token_hash' => null, 'recovered_at' => now()])->save();
            return $merged;
        }

        return DB::transaction(function () use ($source, $sessionId, $activeKey, $tokenHash): ChatbotCart {
            $locked = ChatbotCart::query()->where('id', $source->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals((string) $locked->recovery_token_hash, $tokenHash)) {
                throw ValidationException::withMessages(['recovery_token' => 'The cart recovery token is invalid.']);
            }
            $this->transition($locked, CartStatus::ACTIVE);
            $locked->forceFill([
                'session_id' => $sessionId,
                'active_key' => $activeKey,
                'recovery_token_hash' => null,
                'recovered_at' => now(),
                'abandoned_at' => null,
                'expires_at' => now()->addDays(7),
                'last_activity_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();
            Metrics::increment('cart.recovered');
            event(new ExtensionEvent('cart.recovered', ['cart_uuid' => $locked->uuid]));
            return $this->hydrate($locked);
        });
    }

    public function markConverted(ChatbotCart $cart): ChatbotCart
    {
        return DB::transaction(function () use ($cart): ChatbotCart {
            $locked = ChatbotCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $this->transition($locked, CartStatus::CONVERTED);
            $this->pricing->redeemCouponReservation((int) $locked->id);
            $locked->forceFill([
                'active_key' => null,
                'converted_at' => now(),
                'last_activity_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();
            return $this->hydrate($locked);
        });
    }

    public function expireDue(int $limit = 500): int
    {
        $ids = ChatbotCart::query()
            ->whereIn('status', [CartStatus::ACTIVE, CartStatus::ABANDONED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired): void {
                $cart = ChatbotCart::query()->whereKey($id)->lockForUpdate()->first();
                if (! $cart || ! CartStatus::isMutable((string) $cart->status)) {
                    return;
                }
                $this->expireLockedCart($cart);
                $expired++;
            });
        }

        return $expired;
    }

    private function assertVariantForCart(ChatbotCart $cart, int $variantId): void
    {
        $exists = ProductVariant::query()->whereKey($variantId)->where('active', true)->whereHas('product', fn ($query) => $query->where('chatbot_id', $cart->chatbot_id)->where('active', true))->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['variant_id' => 'The selected product variant is unavailable for this storefront.']);
        }
    }

    private function operate(
        ChatbotCart $cart,
        ?string $idempotencyKey,
        string $operation,
        array $payload,
        Closure $callback,
    ): ChatbotCart {
        $key = $idempotencyKey ? trim($idempotencyKey) : null;
        $requestHash = hash('sha256', json_encode($this->canonicalise([$operation, $payload]), JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($cart, $key, $requestHash, $operation, $callback): ChatbotCart {
                $locked = ChatbotCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
                if ($key) {
                    $existing = CartOperation::query()
                        ->where('cart_id', $locked->id)
                        ->where('idempotency_key', $key)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                            throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for a different cart operation.']);
                        }
                        return $this->hydrate($locked);
                    }
                }

                $result = $callback($locked);
                if ($key) {
                    CartOperation::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'cart_id' => $result->id,
                        'idempotency_key' => $key,
                        'request_hash' => $requestHash,
                        'operation' => $operation,
                        'response' => $this->snapshot($result),
                    ]);
                }

                Metrics::increment('cart.operation', ['operation' => $operation]);
                return $this->hydrate($result);
            });
        } catch (QueryException $exception) {
            if ($key) {
                $existing = CartOperation::query()
                    ->where('cart_id', $cart->id)
                    ->where('idempotency_key', $key)
                    ->first();
                if ($existing && hash_equals((string) $existing->request_hash, $requestHash)) {
                    return $this->hydrate(ChatbotCart::query()->findOrFail($cart->id));
                }
            }
            throw $exception;
        }
    }

    private function persistQuote(ChatbotCart $cart, array $quote): ChatbotCart
    {
        $this->persistLineRecords($cart, $quote['lines']);

        [$legacyProducts, $legacyData] = $this->legacyCartPayload($quote['lines']);
        $nextVersion = (int) $cart->version + 1;
        $cart->forceFill([
            'lines' => $quote['lines'],
            'products' => $legacyProducts,
            'product_data' => $legacyData,
            'currency' => $quote['currency'],
            'coupon_code' => $quote['coupon'],
            'list_subtotal' => $quote['list_subtotal'] ?? $quote['subtotal'],
            'subtotal' => $quote['subtotal'],
            'item_discount_total' => $quote['item_discount'] ?? $quote['discount'],
            'discount_total' => $quote['discount'],
            'tax_total' => $quote['tax'],
            'tax_zone_id' => $quote['tax_zone_id'] ?? null,
            'tax_context_hash' => $quote['tax_context_hash'] ?? null,
            'tax_breakdown' => $quote['tax_breakdown'] ?? [],
            'shipping_subtotal' => $quote['shipping_subtotal'] ?? $quote['shipping'],
            'shipping_discount_total' => $quote['shipping_discount'] ?? 0,
            'shipping_total' => $quote['shipping'],
            'total' => $quote['total'],
            'price_snapshot' => $quote,
            'pricing_snapshot_uuid' => $quote['snapshot_uuid'] ?? null,
            'pricing_snapshot_hash' => $quote['snapshot_hash'] ?? null,
            'calculation_version' => $quote['calculation_version'] ?? PricingRuntime::CALCULATION_VERSION,
            'line_count' => count($quote['lines']),
            'expires_at' => now()->addDays(7),
            'last_activity_at' => now(),
            'version' => $nextVersion,
        ])->save();

        $this->persistPricingSnapshot($cart, $quote);
        event(new ExtensionEvent('cart.updated', $this->snapshot($cart)));
        return $cart;
    }

    private function persistPricingSnapshot(ChatbotCart $cart, array $quote): void
    {
        if (! Schema::hasTable('ext_chatbot_pricing_snapshots')) {
            return;
        }

        PricingSnapshot::query()->firstOrCreate(
            [
                'cart_id' => (int) $cart->id,
                'cart_version' => (int) $cart->version,
            ],
            [
                'uuid' => (string) ($quote['snapshot_uuid'] ?? Str::uuid()),
                'calculation_version' => (string) ($quote['calculation_version'] ?? PricingRuntime::CALCULATION_VERSION),
                'currency' => (string) $quote['currency'],
                'subtotal' => (int) $quote['subtotal'],
                'discount_total' => (int) $quote['discount'],
                'shipping_total' => (int) $quote['shipping'],
                'tax_total' => (int) $quote['tax'],
                'total' => (int) $quote['total'],
                'snapshot_hash' => (string) ($quote['snapshot_hash'] ?? hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR))),
                'calculation' => $quote,
                'created_at' => now(),
            ],
        );
    }

    /** @param list<array<string, mixed>> $lines */
    private function persistLineRecords(ChatbotCart $cart, array $lines): void
    {
        $keys = [];
        foreach ($lines as $line) {
            $lineKey = (string) ($line['line_key'] ?? CartLineKey::make((int) $line['variant_id'], $line['customisation'] ?? []));
            $keys[] = $lineKey;
            $existingUuid = ChatbotCartLine::query()
                ->where('cart_id', $cart->id)
                ->where('line_key', $lineKey)
                ->value('uuid');
            ChatbotCartLine::query()->updateOrCreate(
                ['cart_id' => $cart->id, 'line_key' => $lineKey],
                [
                    'uuid' => $existingUuid ?: (string) Str::uuid(),
                    'product_id' => (int) $line['product_id'],
                    'variant_id' => (int) $line['variant_id'],
                    'sku' => $line['sku'] ?? null,
                    'name' => (string) ($line['name'] ?? 'Product'),
                    'variant_name' => $line['variant_name'] ?? null,
                    'quantity' => (int) $line['quantity'],
                    'list_unit_price' => (int) ($line['list_unit_price'] ?? $line['unit_price'] ?? 0),
                    'unit_price' => (int) ($line['unit_price'] ?? 0),
                    'gross_total' => (int) ($line['gross_total'] ?? ((int) ($line['unit_price'] ?? 0) * (int) $line['quantity'])),
                    'discounts' => $line['discounts'] ?? [],
                    'discount_total' => (int) ($line['discount_total'] ?? 0),
                    'net_before_tax' => (int) ($line['net_before_tax'] ?? max(0, ((int) ($line['unit_price'] ?? 0) * (int) $line['quantity']) - (int) ($line['discount_total'] ?? 0))),
                    'tax_rate_bps' => (int) ($line['tax_rate_bps'] ?? 0),
                    'tax_total' => (int) ($line['tax_total'] ?? 0),
                    'line_total' => (int) ($line['line_total'] ?? ((int) ($line['unit_price'] ?? 0) * (int) $line['quantity'])),
                    'currency' => strtoupper((string) ($line['currency'] ?? $cart->currency ?? 'USD')),
                    'customisation' => $line['customisation'] ?? [],
                    'metadata' => $line['metadata'] ?? [],
                    'price_snapshot' => $line,
                ],
            );
        }

        $delete = ChatbotCartLine::query()->where('cart_id', $cart->id);
        if ($keys !== []) {
            $delete->whereNotIn('line_key', $keys);
        }
        $delete->delete();
    }

    private function materialiseLegacyLines(ChatbotCart $cart): ChatbotCart
    {
        if (! Schema::hasTable('ext_chatbot_cart_lines')) {
            return $cart;
        }
        if (ChatbotCartLine::query()->where('cart_id', $cart->id)->exists()) {
            return $cart;
        }
        $legacyLines = $cart->lines ?? [];
        if ($legacyLines === []) {
            return $cart;
        }

        $complete = collect($legacyLines)->every(
            fn (array $line): bool => isset($line['product_id'], $line['variant_id'], $line['quantity'], $line['unit_price']),
        );
        $lines = $complete
            ? $legacyLines
            : $this->pricing->quote($this->linePayloads($cart), $cart->coupon_code, false, $this->pricingContext($cart))['lines'];
        $this->persistLineRecords($cart, $lines);

        return $cart;
    }

    /** @return list<array<string, mixed>> */
    private function linePayloads(ChatbotCart $cart): array
    {
        $persisted = ChatbotCartLine::query()->where('cart_id', $cart->id)->orderBy('id')->get();
        if ($persisted->isNotEmpty()) {
            return $persisted->map(fn (ChatbotCartLine $line): array => [
                'line_key' => $line->line_key,
                'variant_id' => (int) $line->variant_id,
                'quantity' => (int) $line->quantity,
                'customisation' => $line->customisation ?? [],
                'metadata' => $line->metadata ?? [],
            ])->all();
        }

        return collect($cart->lines ?? [])->map(function (array $line): array {
            $customisation = $line['customisation'] ?? [];
            return [
                'line_key' => $line['line_key'] ?? CartLineKey::make((int) $line['variant_id'], $customisation),
                'variant_id' => (int) $line['variant_id'],
                'quantity' => (int) ($line['quantity'] ?? 1),
                'customisation' => $customisation,
                'metadata' => $line['metadata'] ?? [],
            ];
        })->all();
    }

    private function releaseExcessReservations(ChatbotCart $cart, int $variantId, int $allowedQuantity): void
    {
        $reservations = InventoryReservation::query()
            ->where('cart_id', $cart->id)
            ->where('variant_id', $variantId)
            ->where('status', InventoryReservationState::ACTIVE)
            ->orderByDesc('id')
            ->get();
        $reserved = (int) $reservations->sum('quantity');
        foreach ($reservations as $reservation) {
            if ($reserved <= $allowedQuantity) {
                break;
            }
            $this->inventory->releaseForCart($cart, $reservation, 'cart_line_reduced');
            $reserved -= (int) $reservation->quantity;
        }
    }

    private function releaseAllReservations(ChatbotCart $cart): void
    {
        $reservations = InventoryReservation::query()
            ->where('cart_id', $cart->id)
            ->where('status', InventoryReservationState::ACTIVE)
            ->get();
        foreach ($reservations as $reservation) {
            $this->inventory->releaseForCart($cart, $reservation, 'cart_closed');
        }
    }

    private function expireLockedCart(ChatbotCart $cart): void
    {
        $this->releaseAllReservations($cart);
        $this->pricing->releaseCouponReservation((int) $cart->id);
        $this->transition($cart, CartStatus::EXPIRED);
        $cart->forceFill([
            'active_key' => null,
            'recovery_token_hash' => null,
            'last_activity_at' => now(),
            'version' => (int) $cart->version + 1,
        ])->save();
        Metrics::increment('cart.expired');
        event(new ExtensionEvent('cart.expired', ['cart_uuid' => $cart->uuid]));
    }

    private function transition(ChatbotCart $cart, string $target): void
    {
        if (! CartStatus::canTransition((string) $cart->status, $target)) {
            throw ValidationException::withMessages(['status' => sprintf('Cart cannot transition from %s to %s.', $cart->status, $target)]);
        }
        $cart->status = $target;
    }

    private function assertMutable(ChatbotCart $cart): void
    {
        if (! CartStatus::isMutable((string) $cart->status)) {
            throw ValidationException::withMessages(['cart' => 'This cart can no longer be changed.']);
        }
        if ($cart->expires_at?->isPast() ?? false) {
            throw ValidationException::withMessages(['cart' => 'This cart has expired.']);
        }
    }

    private function pricingContext(ChatbotCart $cart): array
    {
        $metadata = $cart->metadata ?? [];
        $shippingSubtotal = (int) ($cart->shipping_subtotal ?? 0);
        if ($shippingSubtotal === 0) {
            $shippingSubtotal = (int) ($cart->shipping_total ?? 0);
        }

        return [
            'cart_id' => (int) $cart->id,
            'chatbot_id' => (int) $cart->chatbot_id,
            'customer_identity_id' => $cart->customer_identity_id ? (int) $cart->customer_identity_id : null,
            'channel' => $cart->channel,
            'currency' => $cart->currency,
            'shipping_total' => $shippingSubtotal,
            'completed_order_count' => (int) ($metadata['completed_order_count'] ?? 0),
            'prices_include_tax' => $metadata['prices_include_tax'] ?? config('chatbot-ecommerce.pricing.prices_include_tax', false),
            'tax_rates' => $metadata['tax_rates'] ?? config('chatbot-ecommerce.pricing.tax_rates', []),
            'default_tax_rate_bps' => (int) ($metadata['default_tax_rate_bps'] ?? config('chatbot-ecommerce.pricing.default_tax_rate_bps', 0)),
            'shipping_tax_rate_bps' => (int) ($metadata['shipping_tax_rate_bps'] ?? config('chatbot-ecommerce.pricing.shipping_tax_rate_bps', 0)),
            'tax_address' => (array) ($metadata['tax_address'] ?? []),
            'shipping_address' => (array) ($metadata['shipping_address'] ?? []),
            'billing_address' => (array) ($metadata['billing_address'] ?? []),
            'tax_exemption_key' => $metadata['tax_exemption_key'] ?? null,
            'tax_exempt' => (bool) ($metadata['tax_exempt'] ?? false),
        ];
    }

    private function activeKey(int $chatbotId, string $sessionId): string
    {
        return hash('sha256', $chatbotId . '|internal|' . $sessionId);
    }

    private function canonicalise(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalise($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        return $value;
    }

    /** @return array{0: list<int>, 1: array<int, array<string, mixed>>} */
    private function legacyCartPayload(array $lines): array
    {
        $products = [];
        $data = [];
        foreach ($lines as $line) {
            for ($i = 0; $i < (int) $line['quantity']; $i++) {
                $products[] = (int) $line['variant_id'];
            }
            $data[(int) $line['variant_id']] = $line;
        }
        return [$products, $data];
    }

    private function snapshot(ChatbotCart $cart): array
    {
        return [
            'cart_uuid' => $cart->uuid,
            'status' => $cart->status,
            'currency' => $cart->currency,
            'line_count' => (int) $cart->line_count,
            'subtotal' => (int) $cart->subtotal,
            'discount_total' => (int) $cart->discount_total,
            'tax_total' => (int) $cart->tax_total,
            'shipping_total' => (int) $cart->shipping_total,
            'total' => (int) $cart->total,
            'pricing_snapshot_uuid' => $cart->pricing_snapshot_uuid,
            'pricing_snapshot_hash' => $cart->pricing_snapshot_hash,
            'version' => (int) $cart->version,
        ];
    }

    private function hydrate(ChatbotCart $cart): ChatbotCart
    {
        $cart = $cart->refresh();
        $this->materialiseLegacyLines($cart);
        return $cart->load(['cartLines', 'reservations']);
    }
}
