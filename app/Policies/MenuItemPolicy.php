<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Items follow their menu: whoever may edit the menu may manage its items.
 */
class MenuItemPolicy
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MenuPolicy $menus,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->menus->viewAny($user);
    }

    public function view(User $user, MenuItem $item): bool
    {
        return $this->update($user, $item);
    }

    /**
     * Possible when at least one menu is editable; the chosen category is
     * re-checked in MenuContentService's caller.
     */
    public function create(User $user): bool
    {
        $membership = $this->membership($user);

        return $membership !== null && Menu::query()->editableBy($membership)->exists();
    }

    public function update(User $user, MenuItem $item): bool
    {
        $menu = $item->category?->menu;

        return $menu !== null && $this->menus->update($user, $menu);
    }

    public function delete(User $user, MenuItem $item): bool
    {
        return $this->update($user, $item);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    private function membership(User $user): ?TenantMembership
    {
        return $this->context->check() ? $user->membershipFor($this->context->tenant()) : null;
    }
}
