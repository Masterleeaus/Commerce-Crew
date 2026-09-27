<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Middleware;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Support\CommerceSessionAuthority;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequireCommerceSessionAuthority
{
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        $chatbot = $request->route('chatbot');
        $sessionId = $request->route('sessionId');
        if (! $chatbot instanceof Chatbot || ! is_string($sessionId)) {
            return $next($request);
        }

        $token = trim((string) ($request->header('X-Commerce-Session-Token') ?: $request->bearerToken()));
        if ($token === '') {
            abort(401, 'A commerce session authority token is required.');
        }
        try {
            $claims = CommerceSessionAuthority::verify($token, $this->secret());
        } catch (Throwable) {
            abort(401, 'The commerce session authority token is invalid or expired.');
        }

        if ((int) $claims['chatbot_id'] !== (int) $chatbot->getKey()
            || (string) $claims['chatbot_uuid'] !== (string) $chatbot->getAttribute('uuid')
            || ! hash_equals((string) $claims['session_id'], $sessionId)) {
            abort(403, 'The commerce session authority does not cover this chatbot session.');
        }

        $requestOrigin = strtolower((string) (parse_url((string) ($request->headers->get('Origin') ?: $request->headers->get('Referer') ?: ''), PHP_URL_HOST) ?: ''));
        $claimOrigin = strtolower((string) ($claims['origin'] ?? ''));
        if ($requestOrigin !== '' && $claimOrigin !== '' && ! hash_equals($claimOrigin, $requestOrigin)) {
            abort(403, 'The commerce session authority was issued for a different storefront origin.');
        }

        $required = $capability ?: $this->requiredCapability($request);
        if (! CommerceSessionAuthority::allows($claims, $required)) {
            abort(403, 'The commerce session authority does not allow this action.');
        }

        foreach (['chatbot_id' => 'chatbot_id', 'session_id' => 'session_id', 'customer_id' => 'customer_id', 'customer_identity_id' => 'customer_id'] as $input => $claim) {
            if (! $request->has($input)) {
                continue;
            }
            if (! array_key_exists($claim, $claims)) {
                abort(403, "The supplied {$input} is not authorised by this session token.");
            }
            if ((string) $request->input($input) !== (string) $claims[$claim]) {
                abort(403, "The supplied {$input} is outside the signed session authority.");
            }
        }

        $request->attributes->set('commerce_session_claims', $claims);
        $request->attributes->set('commerce_session_capability', $required);

        return $next($request);
    }

    private function requiredCapability(Request $request): string
    {
        $name = strtolower((string) $request->route()?->getName());
        $method = strtoupper($request->method());
        if (str_contains($name, 'support.')) return $method === 'GET' ? 'support:read' : 'support:write';
        if (str_contains($name, 'orders.materialize')) return 'order:materialize';
        if (str_contains($name, 'orders.returns')) return 'returns:prepare';
        if (str_contains($name, 'orders.')) return 'orders:read';
        if (str_contains($name, 'payments.') || str_contains($name, 'bnpl.')) return $method === 'GET' ? 'payment:read' : 'payment:prepare';
        if (str_contains($name, 'checkout.')) return $method === 'GET' ? 'checkout:read' : 'checkout:prepare';
        if (str_contains($name, 'marketplaces.')) return 'marketplace:read';
        if (str_contains($name, 'inventory.')) return $method === 'GET' ? 'inventory:read' : 'inventory:reserve';
        if (str_contains($name, 'shipping.')) return 'shipping:read';
        if (str_contains($name, 'conversation.')) return $method === 'GET' ? 'context:read' : 'context:write';
        if (str_contains($name, 'cart.')) return $method === 'GET' ? 'cart:read' : 'cart:write';

        return $method === 'GET' ? 'catalogue:read' : 'cart:write';
    }

    private function secret(): string
    {
        $secret = (string) config('chatbot-ecommerce.security.session_authority_secret', env('CHATBOT_ECOMMERCE_SESSION_AUTHORITY_SECRET', ''));
        if (strlen($secret) < 32) {
            abort(503, 'Commerce session authority is not configured.');
        }

        return $secret;
    }
}
