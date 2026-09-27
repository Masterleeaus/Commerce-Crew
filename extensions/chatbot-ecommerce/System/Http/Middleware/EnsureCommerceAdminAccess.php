<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Middleware;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantRuntime;
use App\Extensions\ChatbotEcommerce\System\Support\CommercePermissionMap;
use Illuminate\Database\Eloquent\Model;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCommerceAdminAccess
{
    public function __construct(private readonly CommerceTenantRuntime $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(401);
        }
        $permission = CommercePermissionMap::forRoute($request->route()?->getName());
        $request->attributes->set('commerce_permission', $permission);
        $request->attributes->set('commerce_tenant_chatbot_ids', $this->tenants->ownedChatbotIds($request));

        $chatbot = $request->route('chatbot');
        if ($chatbot instanceof Chatbot) {
            $this->tenants->assertChatbotOwner($request, $chatbot);
        }

        $boundTenantRecord = false;
        foreach ((array) $request->route()?->parameters() as $parameter) {
            if ($parameter instanceof Model && ! $parameter instanceof Chatbot) {
                $this->tenants->assertRecord($request, $parameter);
                $boundTenantRecord = true;
            }
        }

        if ($request->has('chatbot_id')) {
            $this->tenants->requestedOwnedChatbotId($request);
        } elseif (! $chatbot instanceof Chatbot && ! $boundTenantRecord && ! in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(422, 'A tenant-scoped chatbot_id is required for this commerce operation.');
        }

        return $next($request);
    }
}
