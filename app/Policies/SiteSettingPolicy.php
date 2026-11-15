<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Platform contact details (email, phone, address, social links) are public
 * on every marketing page: super admins only.
 */
class SiteSettingPolicy
{
    public function update(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
