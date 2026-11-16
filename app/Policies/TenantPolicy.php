<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Two separate sets of abilities on one model; neither implies the other.
 *
 * - Business panel: `manageSettings` (profile, languages, theme,
 *   subscription) for owners of the tenant in TenantContext only. Platform
 *   roles grant nothing here.
 * - Platform management in /admin (launch plan Faz 8): any platform role
 *   lists and views tenants; only super admins suspend, unsuspend, change the
 *   slug, soft-delete and restore. Nothing is ever hard-deleted, and tenants
 *   are created only by registration. Business memberships grant nothing here.
 */
class TenantPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasPlatformRole();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() && ! $tenant->trashed() && $tenant->status === TenantStatus::Active;
    }

    public function unsuspend(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() && ! $tenant->trashed() && $tenant->status === TenantStatus::Suspended;
    }

    public function changeSlug(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() && ! $tenant->trashed();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() && ! $tenant->trashed();
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() && $tenant->trashed();
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function manageSettings(User $user, Tenant $tenant): bool
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            return false;
        }

        return $user->membershipFor($tenant)?->isOwner() ?? false;
    }
}
