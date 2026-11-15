<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Menu;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Menus and their content (categories, items, variants) in the business panel.
 *
 * owner: everything, incl. attaching menus to locations.
 * manager: view/update menus fully inside their branches (canEditMenu()).
 * branch_staff: nothing here (stock status has its own page).
 */
class MenuPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        $role = $this->membership($user)?->role;

        return $role === TenantRole::Owner || $role === TenantRole::Manager;
    }

    public function view(User $user, Menu $menu): bool
    {
        return $this->update($user, $menu);
    }

    public function create(User $user): bool
    {
        return $this->membership($user)?->isOwner() ?? false;
    }

    public function update(User $user, Menu $menu): bool
    {
        return $this->membership($user)?->canEditMenu($menu) ?? false;
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $this->owner($user, $menu);
    }

    public function restore(User $user, Menu $menu): bool
    {
        return $this->owner($user, $menu);
    }

    public function forceDelete(User $user, Menu $menu): bool
    {
        return false;
    }

    /**
     * Attaching/detaching menus to locations, order and serving hours.
     */
    public function manageLocations(User $user, Menu $menu): bool
    {
        return $this->owner($user, $menu);
    }

    private function owner(User $user, Menu $menu): bool
    {
        $membership = $this->membership($user);

        return $membership !== null && $membership->isOwner() && (int) $menu->tenant_id === (int) $membership->tenant_id;
    }

    private function membership(User $user): ?TenantMembership
    {
        return $this->context->check() ? $user->membershipFor($this->context->tenant()) : null;
    }
}
