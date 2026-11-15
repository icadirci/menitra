<?php

declare(strict_types=1);

namespace App\Tenancy\Resolvers;

use App\Services\Menu\MenuCacheKeys;
use App\Tenancy\ResolvedTenant;
use App\Tenancy\ResolvedTenantFactory;
use Illuminate\Http\Request;

/**
 * {slug}.{central domain} → tenant (cached as qrmenu:host:{host}).
 */
final class SubdomainTenantResolver implements TenantResolver
{
    public function __construct(private readonly ResolvedTenantFactory $factory) {}

    public function resolve(Request $request): ?ResolvedTenant
    {
        $host = strtolower($request->getHost());
        $suffix = '.'.strtolower((string) config('tenancy.central_domain'));

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        if ($slug === '' || str_contains($slug, '.') || in_array($slug, config('tenancy.reserved_slugs'), true)) {
            return null;
        }

        return $this->factory->bySlug($slug, MenuCacheKeys::host($host));
    }
}
