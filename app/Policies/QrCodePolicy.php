<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * QR codes follow their branch:
 *
 * owner: everything on every branch (deleting included).
 * manager: view, download, create and edit codes of assigned branches.
 * branch_staff: view and download codes of assigned branches.
 *
 * Deleting is owner-only because it breaks printed codes for good;
 * deactivating is the reversible alternative. The branch a code is created
 * for or moved to is re-checked with manageAt().
 */
class QrCodePolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->membership($user) !== null;
    }

    /**
     * Viewing includes downloading the images.
     */
    public function view(User $user, QrCode $qrCode): bool
    {
        $location = $qrCode->location()->withTrashed()->first();

        return $location !== null && ($this->membership($user)?->canAccessLocation($location) ?? false);
    }

    public function create(User $user): bool
    {
        $membership = $this->membership($user);

        return $membership !== null
            && $this->manages($membership)
            && $membership->accessibleLocations()->exists();
    }

    public function update(User $user, QrCode $qrCode): bool
    {
        $location = $qrCode->location()->withTrashed()->first();

        return $location !== null && $this->manageAt($user, $location);
    }

    public function delete(User $user, QrCode $qrCode): bool
    {
        $membership = $this->membership($user);

        return $membership !== null
            && $membership->isOwner()
            && (int) $qrCode->tenant_id === (int) $membership->tenant_id;
    }

    /**
     * May create a code for, or move a code to, this branch.
     */
    public function manageAt(User $user, Location $location): bool
    {
        $membership = $this->membership($user);

        return $membership !== null
            && $this->manages($membership)
            && $membership->canAccessLocation($location);
    }

    private function manages(TenantMembership $membership): bool
    {
        return in_array($membership->role, [TenantRole::Owner, TenantRole::Manager], true);
    }

    private function membership(User $user): ?TenantMembership
    {
        return $this->context->check() ? $user->membershipFor($this->context->tenant()) : null;
    }
}
