<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryAdjustment;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryItem;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryLocation;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryLocationStock;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryReservation;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;
use App\Extensions\ChatbotEcommerce\System\Support\ExtensionLogger;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryAvailability;
use App\Extensions\ChatbotEcommerce\System\Support\InventoryReservationState;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InventoryRuntime
{
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';
    public function availability(int $variantId, ?int $locationId = null): array
    {
        ProductVariant::query()->findOrFail($variantId);
        if ($locationId !== null) {
            InventoryLocation::query()->findOrFail($locationId);
        }

        $stock = $this->stockQuery($variantId, $locationId)->first();

        if (! $stock) {
            return [
                'variant_id' => $variantId,
                'location_id' => $locationId,
                'quantity' => 0,
                'reserved' => 0,
                'committed' => 0,
                'incoming' => 0,
                'damaged' => 0,
                'safety_stock' => 0,
                'available' => 0,
                'low_stock' => true,
                'version' => 0,
            ];
        }

        return $this->stockPayload($stock, $locationId);
    }

    public function adjust(
        int $variantId,
        int $delta,
        string $reason,
        ?int $locationId = null,
        ?int $expectedVersion = null,
        ?int $actorId = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): InventoryAdjustment {
        if ($delta === 0) {
            throw ValidationException::withMessages(['delta' => 'Inventory adjustment delta cannot be zero.']);
        }

        if ($idempotencyKey) {
            $existing = InventoryAdjustment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($variantId, $delta, $reason, $locationId, $expectedVersion, $actorId, $idempotencyKey, $metadata): InventoryAdjustment {
                ProductVariant::query()->findOrFail($variantId);
                $stock = $this->lockStock($variantId, $locationId);

                if ($expectedVersion !== null && (int) $stock->version !== $expectedVersion) {
                    throw ValidationException::withMessages([
                        'version' => 'Inventory changed after it was loaded. Refresh and retry with the current version.',
                    ]);
                }

                $newQuantity = (int) $stock->quantity + $delta;
                $minimumQuantity = (int) $stock->reserved + (int) $stock->committed + (int) $stock->damaged;

                if ($newQuantity < $minimumQuantity) {
                    throw ValidationException::withMessages([
                        'delta' => 'The adjustment would reduce stock below reserved, committed, or damaged quantities.',
                    ]);
                }

                $stock->forceFill([
                    'quantity' => $newQuantity,
                    'version' => (int) $stock->version + 1,
                ])->save();

                $adjustment = InventoryAdjustment::query()->create([
                    'variant_id' => $variantId,
                    'location_id' => $locationId,
                    'delta' => $delta,
                    'reason' => $reason,
                    'balance_after' => $newQuantity,
                    'version' => (int) $stock->version,
                    'actor_id' => $actorId,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => $metadata,
                ]);

                Metrics::increment('inventory.adjusted', ['reason' => $reason]);
                event(new ExtensionEvent('inventory.adjusted', [
                    'variant_id' => $variantId,
                    'location_id' => $locationId,
                    'delta' => $delta,
                    'balance_after' => $newQuantity,
                    'version' => (int) $stock->version,
                ]));

                return $adjustment;
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey && ($existing = InventoryAdjustment::query()->where('idempotency_key', $idempotencyKey)->first())) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function reserve(
        ChatbotCart $cart,
        int $variantId,
        int $quantity,
        string $idempotencyKey,
        ?int $locationId = null,
        int $ttlMinutes = 15,
        array $metadata = [],
    ): InventoryReservation {
        if ($quantity < 1 || $quantity > 999) {
            throw ValidationException::withMessages(['quantity' => 'Reservation quantity must be between 1 and 999.']);
        }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 191) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A non-empty idempotency key of at most 191 characters is required.',
            ]);
        }

        $existing = InventoryReservation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            $this->assertMatchingReservation($existing, $cart, $variantId, $quantity, $locationId);
            return $existing;
        }

        try {
            return DB::transaction(function () use ($cart, $variantId, $quantity, $idempotencyKey, $locationId, $ttlMinutes, $metadata): InventoryReservation {
                $lockedCart = ChatbotCart::query()->lockForUpdate()->findOrFail($cart->id);
                $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);
                if ($locationId !== null) {
                    InventoryLocation::query()->where('active', true)->findOrFail($locationId);
                }
                $stock = $this->lockStock($variantId, $locationId);
                $activeReservations = $this->lockActiveReservationsForStock($variantId, $locationId);
                $this->expireStaleReservationsForStock($stock, $activeReservations);

                $cartQuantity = (int) collect($lockedCart->lines ?? [])
                    ->filter(fn (array $line): bool => (int) ($line['variant_id'] ?? 0) === $variantId)
                    ->sum(fn (array $line): int => (int) ($line['quantity'] ?? 0));
                if ($cartQuantity < 1) {
                    throw ValidationException::withMessages([
                        'variant_id' => 'The variant must be present in the cart before inventory can be reserved.',
                    ]);
                }

                $alreadyReservedForCart = $activeReservations
                    ->where('cart_id', $lockedCart->id)
                    ->where('expires_at', '>', now())
                    ->sum('quantity');
                if ((int) $alreadyReservedForCart + $quantity > $cartQuantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'The requested reservation exceeds the quantity currently held in the cart.',
                    ]);
                }

                $available = InventoryAvailability::calculate(
                    (int) $stock->quantity,
                    (int) $stock->reserved,
                    (int) $stock->committed,
                    (int) $stock->damaged,
                    (int) $stock->safety_stock,
                );

                if (! $variant->allow_backorder && $available < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => sprintf('Only %d unit(s) are currently available.', $available),
                    ]);
                }

                $stock->forceFill([
                    'reserved' => (int) $stock->reserved + $quantity,
                    'version' => (int) $stock->version + 1,
                ])->save();

                $reservation = InventoryReservation::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'cart_id' => $lockedCart->id,
                    'variant_id' => $variantId,
                    'location_id' => $locationId,
                    'quantity' => $quantity,
                    'status' => InventoryReservationState::ACTIVE,
                    'idempotency_key' => $idempotencyKey,
                    'expires_at' => now()->addMinutes(min(max($ttlMinutes, 1), 120)),
                    'metadata' => $metadata,
                ]);

                Metrics::increment('inventory.reserved');
                event(new ExtensionEvent('inventory.reserved', [
                    'reservation_uuid' => $reservation->uuid,
                    'cart_id' => $lockedCart->id,
                    'variant_id' => $variantId,
                    'location_id' => $locationId,
                    'quantity' => $quantity,
                    'expires_at' => $reservation->expires_at?->toIso8601String(),
                ]));

                return $reservation;
            });
        } catch (QueryException $exception) {
            $existing = InventoryReservation::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $this->assertMatchingReservation($existing, $cart, $variantId, $quantity, $locationId);
                return $existing;
            }

            throw $exception;
        }
    }

    public function release(InventoryReservation $reservation, string $reason = 'released'): InventoryReservation
    {
        return $this->transition($reservation, InventoryReservationState::RELEASED, $reason);
    }

    public function releaseForCart(ChatbotCart $cart, InventoryReservation $reservation, string $reason = 'customer_released'): InventoryReservation
    {
        if ((int) $reservation->cart_id !== (int) $cart->id) {
            throw ValidationException::withMessages(['reservation' => 'The reservation does not belong to this cart.']);
        }

        return $this->release($reservation, $reason);
    }

    public function commit(InventoryReservation $reservation): InventoryReservation
    {
        $result = $this->transition($reservation, InventoryReservationState::COMMITTED);
        if ($result->status !== InventoryReservationState::COMMITTED) {
            throw ValidationException::withMessages([
                'reservation' => 'The inventory reservation expired before it could be committed.',
            ]);
        }

        return $result;
    }

    public function expireDue(int $limit = 500): int
    {
        return $this->expireQuery(InventoryReservation::query(), $limit);
    }

    /** @param list<int> $chatbotIds */
    public function expireDueForChatbots(array $chatbotIds, int $limit = 500): int
    {
        $query = InventoryReservation::query()->whereHas('cart', fn ($builder) => $builder->whereIn('chatbot_id', $chatbotIds));
        return $this->expireQuery($query, $limit);
    }

    private function expireQuery($query, int $limit): int
    {
        $ids = $query
            ->where('status', InventoryReservationState::ACTIVE)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            $reservation = InventoryReservation::query()->find($id);
            if (! $reservation) {
                continue;
            }

            try {
                $result = $this->transition($reservation, InventoryReservationState::EXPIRED, 'expired');
                if ($result->status === InventoryReservationState::EXPIRED) {
                    $expired++;
                }
            } catch (ValidationException) {
                // Another worker completed the reservation first.
            }
        }

        return $expired;
    }

    private function transition(InventoryReservation $reservation, string $target, ?string $reason = null): InventoryReservation
    {
        return DB::transaction(function () use ($reservation, $target, $reason): InventoryReservation {
            $snapshot = InventoryReservation::query()->findOrFail($reservation->id);
            $stock = $this->lockStock(
                (int) $snapshot->variant_id,
                $snapshot->location_id ? (int) $snapshot->location_id : null,
            );
            $locked = InventoryReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($locked->status === $target) {
                return $locked;
            }

            $effectiveTarget = $target;
            $effectiveReason = $reason;
            if (
                $target === InventoryReservationState::COMMITTED
                && $locked->status === InventoryReservationState::ACTIVE
                && ($locked->expires_at?->isPast() ?? false)
            ) {
                $effectiveTarget = InventoryReservationState::EXPIRED;
                $effectiveReason = 'expired';
            }

            if (! InventoryReservationState::canTransition((string) $locked->status, $effectiveTarget)) {
                throw ValidationException::withMessages([
                    'status' => sprintf('Reservation cannot transition from %s to %s.', $locked->status, $effectiveTarget),
                ]);
            }

            $stock->reserved = max(0, (int) $stock->reserved - (int) $locked->quantity);

            if ($effectiveTarget === InventoryReservationState::COMMITTED) {
                $stock->committed = (int) $stock->committed + (int) $locked->quantity;
                $locked->committed_at = now();
            } else {
                $locked->released_at = now();
                $locked->release_reason = $effectiveReason;
            }

            $stock->version = (int) $stock->version + 1;
            $stock->save();

            $locked->status = $effectiveTarget;
            $locked->save();

            Metrics::increment('inventory.reservation_transition', ['status' => $effectiveTarget]);
            event(new ExtensionEvent('inventory.reservation.' . $effectiveTarget, [
                'reservation_uuid' => $locked->uuid,
                'cart_id' => $locked->cart_id,
                'variant_id' => $locked->variant_id,
                'location_id' => $locked->location_id,
                'quantity' => $locked->quantity,
            ]));

            return $locked->refresh();
        });
    }

    private function lockStock(int $variantId, ?int $locationId): InventoryItem|InventoryLocationStock
    {
        if ($locationId !== null) {
            InventoryLocation::query()->findOrFail($locationId);
        }

        $stock = $this->stockQuery($variantId, $locationId)->lockForUpdate()->first();
        if ($stock) {
            return $stock;
        }

        if ($locationId === null) {
            InventoryItem::query()->firstOrCreate(['variant_id' => $variantId]);
        } else {
            InventoryLocationStock::query()->firstOrCreate([
                'variant_id' => $variantId,
                'location_id' => $locationId,
            ]);
        }

        return $this->stockQuery($variantId, $locationId)->lockForUpdate()->firstOrFail();
    }

    private function stockQuery(int $variantId, ?int $locationId)
    {
        if ($locationId === null) {
            return InventoryItem::query()->where('variant_id', $variantId);
        }

        return InventoryLocationStock::query()
            ->where('variant_id', $variantId)
            ->where('location_id', $locationId);
    }

    private function lockActiveReservationsForStock(int $variantId, ?int $locationId)
    {
        return InventoryReservation::query()
            ->where('variant_id', $variantId)
            ->where('status', InventoryReservationState::ACTIVE)
            ->when(
                $locationId === null,
                fn ($query) => $query->whereNull('location_id'),
                fn ($query) => $query->where('location_id', $locationId),
            )
            ->lockForUpdate()
            ->get();
    }

    private function expireStaleReservationsForStock(Model $stock, $activeReservations): void
    {
        $stale = $activeReservations->filter(
            fn (InventoryReservation $reservation): bool => $reservation->expires_at?->isPast() ?? false,
        );

        if ($stale->isEmpty()) {
            return;
        }

        $released = (int) $stale->sum('quantity');
        $stock->reserved = max(0, (int) $stock->reserved - $released);
        $stock->version = (int) $stock->version + 1;
        $stock->save();

        foreach ($stale as $reservation) {
            $reservation->forceFill([
                'status' => InventoryReservationState::EXPIRED,
                'released_at' => now(),
                'release_reason' => 'expired',
            ])->save();
        }

        ExtensionLogger::info('Expired stale inventory reservations while locking stock.', [
            'variant_id' => $stock->variant_id,
            'location_id' => $stock instanceof InventoryLocationStock ? $stock->location_id : null,
            'count' => $stale->count(),
            'quantity' => $released,
        ]);
    }

    private function assertMatchingReservation(
        InventoryReservation $reservation,
        ChatbotCart $cart,
        int $variantId,
        int $quantity,
        ?int $locationId,
    ): void {
        if (
            (int) $reservation->cart_id !== (int) $cart->id
            || (int) $reservation->variant_id !== $variantId
            || (int) $reservation->quantity !== $quantity
            || ($reservation->location_id === null ? null : (int) $reservation->location_id) !== $locationId
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The idempotency key was already used for a different inventory reservation.',
            ]);
        }
    }

    private function stockPayload(InventoryItem|InventoryLocationStock $stock, ?int $locationId): array
    {
        $available = InventoryAvailability::calculate(
            (int) $stock->quantity,
            (int) $stock->reserved,
            (int) $stock->committed,
            (int) $stock->damaged,
            (int) $stock->safety_stock,
        );

        return [
            'variant_id' => (int) $stock->variant_id,
            'location_id' => $locationId,
            'quantity' => (int) $stock->quantity,
            'reserved' => (int) $stock->reserved,
            'committed' => (int) $stock->committed,
            'incoming' => (int) $stock->incoming,
            'damaged' => (int) $stock->damaged,
            'safety_stock' => (int) $stock->safety_stock,
            'available' => $available,
            'low_stock' => $available <= (int) $stock->low_stock_threshold,
            'version' => (int) $stock->version,
        ];
    }
}
