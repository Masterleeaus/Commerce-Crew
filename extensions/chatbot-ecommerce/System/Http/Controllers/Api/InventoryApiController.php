<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryLocation;
use App\Extensions\ChatbotEcommerce\System\Models\InventoryReservation;
use App\Extensions\ChatbotEcommerce\System\Models\ProductVariant;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CartRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\InventoryRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InventoryApiController extends Controller
{
    public function locations(Request $request): JsonResponse
    {
        $query = app(CommerceTenantRuntime::class)->scope(InventoryLocation::query(), $request)
            ->when($request->filled('chatbot_id'), fn ($builder) => $builder->where('chatbot_id', $request->integer('chatbot_id')))
            ->when($request->has('active'), fn ($builder) => $builder->where('active', $request->boolean('active')))
            ->orderBy('priority')
            ->orderBy('name');

        return response()->json([
            'data' => $query->paginate(min(max($request->integer('per_page', 50), 1), 100)),
        ]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chatbot_id' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validated['chatbot_id'] = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        $code = strtolower($validated['code']);
        $query = app(CommerceTenantRuntime::class)->scope(InventoryLocation::query(), $request)->where('code', $code);
        isset($validated['chatbot_id'])
            ? $query->where('chatbot_id', (int) $validated['chatbot_id'])
            : $query->whereNull('chatbot_id');

        $location = $query->first();
        if (! $location) {
            $location = InventoryLocation::query()->create([
                'chatbot_id' => $validated['chatbot_id'] ?? null,
                'code' => $code,
                'name' => $validated['name'],
                'active' => $validated['active'] ?? true,
                'priority' => $validated['priority'] ?? 100,
                'metadata' => $validated['metadata'] ?? [],
            ]);
        }

        return response()->json(['data' => $location], $location->wasRecentlyCreated ? 201 : 200);
    }

    public function updateLocation(InventoryLocation $location, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'metadata' => ['sometimes', 'array'],
        ]);

        if (isset($validated['code'])) {
            $code = strtolower($validated['code']);
            $duplicate = InventoryLocation::query()
                ->where($location->getKeyName(), '!=', $location->getKey())
                ->where('code', $code)
                ->when(
                    $location->chatbot_id === null,
                    fn ($builder) => $builder->whereNull('chatbot_id'),
                    fn ($builder) => $builder->where('chatbot_id', $location->chatbot_id),
                )
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['code' => 'This inventory location code is already in use.']);
            }

            $validated['code'] = $code;
        }

        $location->fill($validated)->save();

        return response()->json(['data' => $location->refresh()]);
    }

    public function show(Chatbot $chatbot, string $sessionId, int $variant, Request $request, InventoryRuntime $runtime): JsonResponse
    {
        $record = ProductVariant::query()->with('product')->findOrFail($variant);
        if ((int) $record->product?->chatbot_id !== (int) $chatbot->id) {
            abort(404);
        }
        $locationId = $request->integer('location_id') ?: null;
        if ($locationId !== null && ! InventoryLocation::query()->whereKey($locationId)->where('chatbot_id', $chatbot->id)->exists()) {
            abort(404);
        }

        return response()->json(['data' => $runtime->availability($variant, $locationId)]);
    }

    public function reserve(
        Chatbot $chatbot,
        string $sessionId,
        Request $request,
        CartRuntime $carts,
        InventoryRuntime $inventory,
    ): JsonResponse {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1', 'exists:ext_chatbot_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'location_id' => ['nullable', 'integer', 'min:1', 'exists:ext_chatbot_inventory_locations,id'],
            'ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $variantRecord = ProductVariant::query()->with('product')->findOrFail((int) $validated['variant_id']);
        if ((int) $variantRecord->product?->chatbot_id !== (int) $chatbot->id) {
            abort(404);
        }
        if (isset($validated['location_id']) && ! InventoryLocation::query()->whereKey((int) $validated['location_id'])->where('chatbot_id', $chatbot->id)->exists()) {
            abort(404);
        }

        $idempotencyKey = trim((string) $request->header(InventoryRuntime::IDEMPOTENCY_HEADER));
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency-Key header is required.']);
        }
        if (strlen($idempotencyKey) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency-Key cannot exceed 191 characters.']);
        }

        $cart = $carts->getOrCreate((int) $chatbot->id, $sessionId, $chatbot->user_id);
        $reservation = $inventory->reserve(
            $cart,
            (int) $validated['variant_id'],
            (int) $validated['quantity'],
            $idempotencyKey,
            isset($validated['location_id']) ? (int) $validated['location_id'] : null,
            (int) ($validated['ttl_minutes'] ?? 15),
            $validated['metadata'] ?? [],
        );

        return response()->json(['data' => $reservation], 201);
    }

    public function release(
        Chatbot $chatbot,
        string $sessionId,
        InventoryReservation $reservation,
        CartRuntime $carts,
        InventoryRuntime $inventory,
    ): JsonResponse {
        $cart = $carts->getOrCreate((int) $chatbot->id, $sessionId, $chatbot->user_id);

        return response()->json([
            'data' => $inventory->releaseForCart($cart, $reservation),
        ]);
    }

    public function adjust(int $variant, Request $request, InventoryRuntime $runtime): JsonResponse
    {
        $validated = $request->validate([
            'chatbot_id' => ['required', 'integer', 'min:1'],
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:100'],
            'location_id' => ['nullable', 'integer', 'min:1', 'exists:ext_chatbot_inventory_locations,id'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $chatbotId = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);
        $variantRecord = ProductVariant::query()->with('product')->findOrFail($variant);
        if ((int) $variantRecord->product?->chatbot_id !== $chatbotId) {
            abort(404);
        }
        if (isset($validated['location_id']) && ! InventoryLocation::query()->whereKey((int) $validated['location_id'])->where('chatbot_id', $chatbotId)->exists()) {
            abort(404);
        }

        $actorIdentifier = $request->user()?->getAuthIdentifier();
        $actorId = is_numeric($actorIdentifier) ? (int) $actorIdentifier : null;
        $idempotencyKey = trim((string) $request->header(InventoryRuntime::IDEMPOTENCY_HEADER));
        if (strlen($idempotencyKey) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency-Key cannot exceed 191 characters.']);
        }

        $adjustment = $runtime->adjust(
            $variant,
            (int) $validated['delta'],
            $validated['reason'],
            isset($validated['location_id']) ? (int) $validated['location_id'] : null,
            isset($validated['expected_version']) ? (int) $validated['expected_version'] : null,
            $actorId,
            $idempotencyKey !== '' ? $idempotencyKey : null,
            $validated['metadata'] ?? [],
        );

        return response()->json(['data' => $adjustment], 201);
    }

    public function commit(InventoryReservation $reservation, InventoryRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $runtime->commit($reservation)]);
    }

    public function expire(Request $request, InventoryRuntime $runtime): JsonResponse
    {
        $validated = $request->validate([
            'chatbot_id' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);
        $chatbotId = app(CommerceTenantRuntime::class)->requestedOwnedChatbotId($request);

        return response()->json([
            'data' => ['expired' => $runtime->expireDueForChatbots([$chatbotId], (int) ($validated['limit'] ?? 500))],
        ]);
    }
}
