<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Tenant-wide settings in the business panel (profile, languages, theme,
 * subscription): owners of the tenant in TenantContext only. Platform roles
 * grant nothing here.
 */
class TenantPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function manageSettings(User $user, Tenant $tenant): bool
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            return false;
        }

        return $user->membershipFor($tenant)?->isOwner() ?? false;
    }
}
