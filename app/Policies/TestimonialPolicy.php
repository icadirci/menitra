<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Marketing site content: any platform role (super admin or platform staff).
 */
class TestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function view(User $user, Testimonial $record): bool
    {
        return $user->hasPlatformRole();
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function update(User $user, Testimonial $record): bool
    {
        return $user->hasPlatformRole();
    }

    public function reorder(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function delete(User $user, Testimonial $record): bool
    {
        return $user->hasPlatformRole();
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }
}
