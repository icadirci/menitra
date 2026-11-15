<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

/**
 * Marketing site content: any platform role (super admin or platform staff).
 */
class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function view(User $user, Faq $record): bool
    {
        return $user->hasPlatformRole();
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function update(User $user, Faq $record): bool
    {
        return $user->hasPlatformRole();
    }

    public function reorder(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function delete(User $user, Faq $record): bool
    {
        return $user->hasPlatformRole();
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }
}
