<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Team members in the business panel (launch plan Faz 7): owners of the
 * tenant in TenantContext only. The owner's own membership can't be edited,
 * deactivated or removed (single owner, no owner transfer). Platform roles
 * grant nothing here. TeamService checks the same rules again.
 */
class TenantMembershipPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->owner($user) !== null;
    }

    public function view(User $user, TenantMembership $member): bool
    {
        return $this->owner($user) !== null && $this->inTenant($member);
    }

    public function update(User $user, TenantMembership $member): bool
    {
        $owner = $this->owner($user);

        return $owner !== null && $this->inTenant($member) && ! $member->isOwner() && ! $member->is($owner);
    }

    public function delete(User $user, TenantMembership $member): bool
    {
        return $this->update($user, $member);
    }

    private function inTenant(TenantMembership $member): bool
    {
        return (int) $member->tenant_id === $this->context->id();
    }

    private function owner(User $user): ?TenantMembership
    {
        if (! $this->context->check()) {
            return null;
        }

        $membership = $user->membershipFor($this->context->tenant());

        return $membership?->isOwner() ? $membership : null;
    }
}
