<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShopOrder;
use App\Models\User;

/**
 * Shop orders in /admin: any platform role reads, only super admins change
 * their state. Nothing is created, edited or deleted directly. Businesses see
 * their own orders in /app through the tenant scope and
 * TenantPolicy::manageSettings (owner-only).
 */
class ShopOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function view(User $user, ShopOrder $order): bool
    {
        return $user->hasPlatformRole();
    }

    public function manage(User $user, ShopOrder $order): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ShopOrder $order): bool
    {
        return false;
    }

    public function delete(User $user, ShopOrder $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
