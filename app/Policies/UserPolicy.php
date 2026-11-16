<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * User management in /admin (launch plan Faz 8). Any platform role lists and
 * views users and resends verification mails; everything else is super-admin
 * only. Users are never deleted (deactivate instead), and nobody changes,
 * deactivates or impersonates their own account.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPlatformRole();
    }

    /**
     * Creating platform users (business accounts come from registration and
     * invitations only).
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function resendVerification(User $user, User $model): bool
    {
        return $user->hasPlatformRole() && ! $model->hasVerifiedEmail() && ! $model->isDeactivated();
    }

    public function changePlatformRole(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && ! $user->is($model);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && ! $user->is($model) && ! $model->isDeactivated();
    }

    public function reactivate(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $model->isDeactivated();
    }

    /**
     * Only into business users: never into a platform user or oneself.
     */
    public function impersonate(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            && ! $user->is($model)
            && ! $model->hasPlatformRole()
            && ! $model->isDeactivated();
    }
}
