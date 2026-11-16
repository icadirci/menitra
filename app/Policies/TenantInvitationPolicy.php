<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TenantInvitation;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Team invitations in the business panel (launch plan Faz 7): only owners of
 * the tenant in TenantContext list, send, resend and revoke them. Accepting
 * is not a panel action (/invitations/{token}).
 */
class TenantInvitationPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function create(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function update(User $user, TenantInvitation $invitation): bool
    {
        return $this->isOwner($user) && (int) $invitation->tenant_id === $this->context->id() && $invitation->isOpen();
    }

    public function delete(User $user, TenantInvitation $invitation): bool
    {
        return $this->update($user, $invitation);
    }

    private function isOwner(User $user): bool
    {
        return $this->context->check() && ($user->membershipFor($this->context->tenant())?->isOwner() ?? false);
    }
}
