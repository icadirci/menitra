<?php

declare(strict_types=1);

namespace App\Tenancy\Resolvers;

use App\Services\Menu\MenuCacheKeys;
use App\Tenancy\ResolvedTenant;
use App\Tenancy\ResolvedTenantFactory;
use Illuminate\Http\Request;

/**
 * {central domain}/m/{slug}/… → tenant (cached as qrmenu:slug:{slug}).
 */
final class PathTenantResolver implements TenantResolver
{
    public function __construct(private readonly ResolvedTenantFactory $factory) {}

    public function resolve(Request $request): ?ResolvedTenant
    {
        if (strtolower($request->getHost()) !== strtolower((string) config('tenancy.central_domain'))) {
            return null;
        }

        $slug = $request->route('tenant');

        return is_string($slug) ? $this->factory->bySlug(strtolower($slug), MenuCacheKeys::slug(strtolower($slug))) : null;
    }
}
