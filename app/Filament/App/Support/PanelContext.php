<?php

declare(strict_types=1);

namespace App\Filament\App\Support;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * The current tenant (TenantContext) and the signed-in user's membership in
 * it, for panel pages and resources. Never resolves a tenant itself.
 */
final class PanelContext
{
    public static function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }

    public static function membership(): ?TenantMembership
    {
        $context = app(TenantContext::class);
        $user = auth()->user();

        return $context->check() && $user instanceof User ? $user->membershipFor($context->tenant()) : null;
    }

    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
