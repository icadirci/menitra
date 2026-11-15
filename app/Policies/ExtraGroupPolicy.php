<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExtraGroup;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Extra groups are shared across every menu of the tenant: owners only.
 */
class ExtraGroupPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->owner($user);
    }

    public function view(User $user, ExtraGroup $group): bool
    {
        return $this->owner($user) && $this->context->id() === (int) $group->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->owner($user);
    }

    public function update(User $user, ExtraGroup $group): bool
    {
        return $this->view($user, $group);
    }

    public function delete(User $user, ExtraGroup $group): bool
    {
        return $this->view($user, $group);
    }

    public function deleteAny(User $user): bool
    {
        return $this->owner($user);
    }

    private function owner(User $user): bool
    {
        return $this->context->check() && ($user->membershipFor($this->context->tenant())?->isOwner() ?? false);
    }
}
