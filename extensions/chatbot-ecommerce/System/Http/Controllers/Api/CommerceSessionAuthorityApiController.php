<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Support\CommerceSessionAuthority;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

final class CommerceSessionAuthorityApiController extends Controller
{
    /** @var list<string> */
    private const PUBLIC_CAPABILITIES = [
        'catalogue:read', 'cart:read', 'cart:write', 'inventory:read', 'inventory:reserve',
        'shipping:read', 'checkout:read', 'checkout:prepare', 'payment:read', 'payment:prepare',
        'orders:read', 'order:materialize', 'returns:prepare', 'context:read', 'context:write', 'support:read',
        'support:write', 'marketplace:read',
    ];

    public function issue(Chatbot $chatbot, Request $request): JsonResponse
    {
        abort_unless((bool) $chatbot->getAttribute('active'), 404);
        $data = $request->validate([
            'session_id' => ['sometimes', 'string', 'min:16', 'max:191', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'capabilities' => ['sometimes', 'array', 'min:1', 'max:20'],
            'capabilities.*' => ['string', Rule::in(self::PUBLIC_CAPABILITIES)],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'ttl_seconds' => ['sometimes', 'integer', 'min:300', 'max:86400'],
        ]);

        $owner = $request->user() && (int) $request->user()->getAuthIdentifier() === (int) $chatbot->getAttribute('user_id');
        if (! $owner) {
            $this->assertTrustedOrigin($chatbot, $request);
            unset($data['customer_id'], $data['session_id']);
        }

        $capabilities = array_values(array_intersect((array) ($data['capabilities'] ?? self::PUBLIC_CAPABILITIES), self::PUBLIC_CAPABILITIES));
        $ttl = (int) ($data['ttl_seconds'] ?? config('chatbot-ecommerce.security.session_authority_ttl_seconds', 1800));
        $claims = [
            'chatbot_id' => (int) $chatbot->getKey(),
            'chatbot_uuid' => (string) $chatbot->getAttribute('uuid'),
            'session_id' => (string) ($data['session_id'] ?? Str::uuid()),
            'capabilities' => $capabilities,
            'origin' => $this->originHost($request),
        ];
        if ($owner && isset($data['customer_id'])) {
            $claims['customer_id'] = (int) $data['customer_id'];
        }
        $token = CommerceSessionAuthority::issue($claims, $this->secret(), $ttl);

        return response()->json(['data' => [
            'token' => $token,
            'token_type' => 'CommerceSession',
            'expires_in' => $ttl,
            'session_id' => $claims['session_id'],
            'capabilities' => $capabilities,
        ]], 201);
    }

    private function assertTrustedOrigin(Chatbot $chatbot, Request $request): void
    {
        $origin = $this->originHost($request);
        if ($origin === '') {
            abort(403, 'A trusted storefront origin is required.');
        }
        $trusted = $chatbot->getAttribute('trusted_domains');
        if (is_string($trusted)) {
            $trusted = json_decode($trusted, true) ?: preg_split('/\s*,\s*/', $trusted, -1, PREG_SPLIT_NO_EMPTY);
        }
        $trusted = array_values(array_filter(array_map(static function ($domain): string {
            $domain = strtolower(trim((string) $domain));
            $host = parse_url(str_contains($domain, '://') ? $domain : 'https://' . $domain, PHP_URL_HOST);
            return strtolower((string) ($host ?: $domain));
        }, (array) $trusted)));
        if ($trusted === []) {
            $trusted = [strtolower((string) $request->getHost())];
        }
        $allowed = false;
        foreach ($trusted as $domain) {
            if ($origin === $domain || str_ends_with($origin, '.' . ltrim($domain, '.'))) {
                $allowed = true;
                break;
            }
        }
        abort_unless($allowed, 403, 'This origin is not trusted for the chatbot.');
    }

    private function originHost(Request $request): string
    {
        $origin = (string) ($request->headers->get('Origin') ?: $request->headers->get('Referer') ?: '');
        return strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));
    }

    private function secret(): string
    {
        $secret = (string) config('chatbot-ecommerce.security.session_authority_secret', env('CHATBOT_ECOMMERCE_SESSION_AUTHORITY_SECRET', ''));
        abort_if(strlen($secret) < 32, 503, 'Commerce session authority is not configured.');

        return $secret;
    }
}
