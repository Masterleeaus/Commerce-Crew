<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceCredentialRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CommerceCredentialAdminApiController extends Controller
{
    public function index(Chatbot $chatbot, CommerceCredentialRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => [
            $runtime->summary($chatbot, 'shopify'),
            $runtime->summary($chatbot, 'woocommerce'),
        ]]);
    }

    public function show(Chatbot $chatbot, string $provider, CommerceCredentialRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $runtime->summary($chatbot, $provider)]);
    }

    public function store(Chatbot $chatbot, string $provider, Request $request, CommerceCredentialRuntime $runtime): JsonResponse
    {
        $data = $this->validated($request, $provider);
        $record = $runtime->store(
            $chatbot,
            $provider,
            $data['credentials'],
            $data['configuration'] ?? [],
            $data['credential_reference'] ?? null,
            (int) $request->user()->getAuthIdentifier(),
        );

        return response()->json(['data' => $runtime->summary($chatbot, $record->provider)], 201);
    }

    public function rotate(Chatbot $chatbot, string $provider, Request $request, CommerceCredentialRuntime $runtime): JsonResponse
    {
        $data = $this->validated($request, $provider);
        $record = $runtime->rotate(
            $chatbot,
            $provider,
            $data['credentials'],
            $data['configuration'] ?? [],
            $data['credential_reference'] ?? null,
            (int) $request->user()->getAuthIdentifier(),
        );

        return response()->json(['data' => $runtime->summary($chatbot, $record->provider)]);
    }

    public function revoke(Chatbot $chatbot, string $provider, Request $request, CommerceCredentialRuntime $runtime): JsonResponse
    {
        $record = $runtime->revoke($chatbot, $provider, (int) $request->user()->getAuthIdentifier());

        return response()->json(['data' => $runtime->summary($chatbot, $record->provider)]);
    }

    public function test(Chatbot $chatbot, string $provider, Request $request, CommerceCredentialRuntime $runtime): JsonResponse
    {
        return response()->json(['data' => $runtime->testConnection($chatbot, $provider, (int) $request->user()->getAuthIdentifier())]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, string $provider): array
    {
        $provider = strtolower($provider);
        $rules = [
            'credential_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'configuration' => ['sometimes', 'array'],
            'configuration.domain' => ['sometimes', 'required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
        ];
        if ($provider === 'shopify') {
            $rules['credentials.access_token'] = ['required', 'string', 'min:8', 'max:4096'];
        } else {
            $rules['credentials.consumer_key'] = ['required', 'string', 'min:8', 'max:4096'];
            $rules['credentials.consumer_secret'] = ['required', 'string', 'min:8', 'max:4096'];
        }
        $request->validate(['provider' => ['sometimes', Rule::in(['shopify', 'woocommerce'])]]);

        return $request->validate($rules);
    }
}
