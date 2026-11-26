<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Shipping fees and the shop switch: super admins only.
 */
class ShopSettingPolicy
{
    public function update(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
