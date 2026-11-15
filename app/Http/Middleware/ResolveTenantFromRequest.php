<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\ResolvedTenant;
use App\Tenancy\Resolvers\TenantResolverPipeline;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public menu routes: identifies the tenant by host or path slug (never by a
 * client-supplied id) and stores the ResolvedTenant on the request. Unknown
 * tenants are a 404. TenantContext is set only when data must be loaded.
 */
class ResolveTenantFromRequest
{
    public const string ATTRIBUTE = 'resolvedTenant';

    public function __construct(private readonly TenantResolverPipeline $pipeline) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->pipeline->resolve($request);

        abort_if($tenant === null, 404);

        $request->attributes->set(self::ATTRIBUTE, $tenant);

        return $next($request);
    }

    public static function tenant(Request $request): ResolvedTenant
    {
        $tenant = $request->attributes->get(self::ATTRIBUTE);

        abort_unless($tenant instanceof ResolvedTenant, 404);

        return $tenant;
    }
}
