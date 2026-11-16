<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Menu\MenuUrlGenerator;
use App\Tenancy\ResolvedTenant;
use App\Tenancy\ResolvedTenantFactory;
use App\Tenancy\Resolvers\TenantResolverPipeline;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public menu routes: identifies the tenant by host or path slug (never by a
 * client-supplied id) and stores the ResolvedTenant on the request. A former
 * slug of a tenant (TenantSlugService) answers 301 to the same page under the
 * current slug; other unknown tenants are a 404. TenantContext is set only
 * when data must be loaded.
 */
class ResolveTenantFromRequest
{
    public const string ATTRIBUTE = 'resolvedTenant';

    public function __construct(
        private readonly TenantResolverPipeline $pipeline,
        private readonly ResolvedTenantFactory $tenants,
        private readonly MenuUrlGenerator $urls,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->pipeline->resolve($request);

        if ($tenant === null) {
            return $this->redirectFormerSlug($request) ?? abort(404);
        }

        $request->attributes->set(self::ATTRIBUTE, $tenant);

        return $next($request);
    }

    /**
     * Same page, same query string, current slug. Cached briefly only, since
     * a slug may later be given back to its tenant.
     */
    private function redirectFormerSlug(Request $request): ?Response
    {
        $former = $request->route('tenant');
        $current = is_string($former) ? $this->tenants->currentSlugFor(strtolower($former)) : null;

        if ($current === null) {
            return null;
        }

        $location = $request->route('location');
        $menu = $request->route('menu');

        $url = match (true) {
            ! $request->routeIs('menu.subdomain.*') => $this->urls->path($current, is_string($location) ? $location : null, is_string($menu) ? $menu : null),
            is_string($location) && is_string($menu) => $this->urls->menu($current, $location, $menu),
            is_string($location) => $this->urls->location($current, $location),
            default => $this->urls->tenant($current),
        };

        $query = $request->getQueryString();

        return redirect()->away($url.($query !== null ? '?'.$query : ''), 301)
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public static function tenant(Request $request): ResolvedTenant
    {
        $tenant = $request->attributes->get(self::ATTRIBUTE);

        abort_unless($tenant instanceof ResolvedTenant, 404);

        return $tenant;
    }
}
