<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Payment methods and the bank account for transfers: super admins only.
 */
class PaymentSettingPolicy
{
    public function update(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
