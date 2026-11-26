<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Bank transfers in /admin: any platform role reads, only super admins
 * approve or reject them. Payments are never created, edited or deleted
 * directly. Businesses see their own payments in /app through the tenant
 * scope and TenantPolicy::manageSettings (owner-only).
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPlatformRole();
    }

    public function review(User $user, Payment $payment): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
