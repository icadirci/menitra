<?php

declare(strict_types=1);

namespace App\Tenancy\Resolvers;

use App\Tenancy\ResolvedTenant;
use Illuminate\Http\Request;

/**
 * Runs the public tenant resolvers in order. A custom-domain resolver slots
 * in here later.
 */
final class TenantResolverPipeline
{
    /** @var list<TenantResolver> */
    private array $resolvers;

    public function __construct(SubdomainTenantResolver $subdomain, PathTenantResolver $path)
    {
        $this->resolvers = [$subdomain, $path];
    }

    public function resolve(Request $request): ?ResolvedTenant
    {
        foreach ($this->resolvers as $resolver) {
            if ($tenant = $resolver->resolve($request)) {
                return $tenant;
            }
        }

        return null;
    }
}
