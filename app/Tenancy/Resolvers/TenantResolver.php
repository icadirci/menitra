<?php

declare(strict_types=1);

namespace App\Tenancy\Resolvers;

use App\Tenancy\ResolvedTenant;
use Illuminate\Http\Request;

interface TenantResolver
{
    public function resolve(Request $request): ?ResolvedTenant;
}
