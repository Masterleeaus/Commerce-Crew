<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Queue\Middleware;

use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantContext;

final class RestoreCommerceTenantContext
{
    public function __construct(private readonly CommerceTenantContext $context) {}

    public function handle(object $job, callable $next): mixed
    {
        $tenant = method_exists($job, 'commerceTenantContext') ? (array) $job->commerceTenantContext() : [];

        return $this->context->run($tenant, static fn (): mixed => $next($job));
    }
}
