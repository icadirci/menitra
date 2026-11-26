<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShopProduct;
use App\Models\User;

/**
 * Products and their prices: super admins only. Never deleted (order lines
 * reference them); deactivate instead.
 */
class ShopProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, ShopProduct $product): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, ShopProduct $product): bool
    {
        return $user->isSuperAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, ShopProduct $product): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
