<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\ChatbotEcommerce\System\Services\CommerceRoleRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommerceRoleApiController extends Controller
{
    public function definitions(CommerceRoleRuntime $roles): JsonResponse
    {
        return response()->json(['data' => $roles->definitions()]);
    }

    public function resolve(Request $request, CommerceRoleRuntime $roles): JsonResponse
    {
        $validated = $request->validate([
            'actor_type' => ['required', 'in:customer,seller,unknown'],
            'requested_role' => ['sometimes', 'nullable', 'in:shopping_assistant,seller_steward,customer_communications'],
            'authenticated' => ['sometimes', 'boolean'],
            'channel' => ['sometimes', 'string', 'max:60'],
            'intent' => ['sometimes', 'nullable', 'string', 'max:100'],
            'inbound_support' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => ['role' => $roles->resolve($validated)]]);
    }
}
