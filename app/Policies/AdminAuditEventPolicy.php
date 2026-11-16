<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdminAuditEvent;
use App\Models\User;

/**
 * The admin audit log is readable by every platform role and never changed.
 */
class AdminAuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole();
    }

    public function view(User $user, AdminAuditEvent $event): bool
    {
        return $user->hasPlatformRole();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AdminAuditEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, AdminAuditEvent $event): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
