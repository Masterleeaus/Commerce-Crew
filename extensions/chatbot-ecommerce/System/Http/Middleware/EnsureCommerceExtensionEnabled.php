<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Middleware;

use App\Extensions\ChatbotEcommerce\System\Services\CommerceLifecycleRuntime;
use Closure;
use Illuminate\Http\Request;

final class EnsureCommerceExtensionEnabled
{
    public function __construct(private readonly CommerceLifecycleRuntime $lifecycle) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->lifecycle->isEnabled()) {
            return response()->json(['message' => 'The ecommerce extension is currently disabled.', 'status' => $this->lifecycle->status()], 503);
        }
        return $next($request);
    }
}
