<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Location;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Branch-level authorization for the business panel. Decisions are based on
 * the user's membership in the current tenant (TenantContext); without a
 * context, or for a location of another tenant, everything is denied.
 *
 * owner: everything on every location of the tenant (incl. the default branch).
 * manager: view and update assigned locations (details, hours, special days).
 * branch_staff: view assigned locations.
 */
class LocationPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->membership($user) !== null;
    }

    public function view(User $user, Location $location): bool
    {
        return $this->membership($user)?->canAccessLocation($location) ?? false;
    }

    public function create(User $user): bool
    {
        return $this->membership($user)?->isOwner() ?? false;
    }

    public function update(User $user, Location $location): bool
    {
        $membership = $this->membership($user);

        return $membership !== null
            && in_array($membership->role, [TenantRole::Owner, TenantRole::Manager], true)
            && $membership->canAccessLocation($location);
    }

    public function delete(User $user, Location $location): bool
    {
        return $this->ownerOf($user, $location);
    }

    public function restore(User $user, Location $location): bool
    {
        return $this->ownerOf($user, $location);
    }

    /**
     * Choosing the default branch is tenant-wide: owners only.
     */
    public function setDefault(User $user, Location $location): bool
    {
        return $this->ownerOf($user, $location);
    }

    /**
     * Tenant data is never purged from the panel.
     */
    public function forceDelete(User $user, Location $location): bool
    {
        return false;
    }

    private function ownerOf(User $user, Location $location): bool
    {
        $membership = $this->membership($user);

        return $membership !== null && $membership->isOwner() && $membership->canAccessLocation($location);
    }

    private function membership(User $user): ?TenantMembership
    {
        return $this->context->check() ? $user->membershipFor($this->context->tenant()) : null;
    }
}
