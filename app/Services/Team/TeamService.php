<?php

declare(strict_types=1);

namespace App\Services\Team;

use App\Enums\PlanFeature;
use App\Enums\TenantRole;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\TenantMembership;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Members of the current tenant (launch plan Faz 7, CLAUDE.md §3.16):
 * role and branch changes, deactivating, reactivating and removing.
 *
 * Owner-only; the caller authorizes (TenantMembershipPolicy) and the service
 * checks again. The owner's own membership is never changed here: there is
 * exactly one owner, who can't be demoted, deactivated or removed (no owner
 * transfer in the first version). Managers and branch staff always keep at
 * least one branch. A deactivated member loses access on the next request
 * (User::membershipFor() requires is_active).
 */
final class TeamService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
        private readonly BranchSelection $branches,
    ) {}

    /**
     * @param  array<mixed>  $locationIds
     *
     * @throws ValidationException
     */
    public function update(TenantMembership $owner, TenantMembership $member, TenantRole|string|null $role, array $locationIds): TenantMembership
    {
        $this->assertCanManage($owner, $member);

        $role = $role instanceof TenantRole ? $role : TenantRole::tryFrom((string) $role);

        if ($role === null || $role === TenantRole::Owner) {
            throw ValidationException::withMessages(['role' => __('team.errors.role')]);
        }

        $locations = $this->branches->resolve($locationIds);

        return DB::transaction(function () use ($member, $role, $locations): TenantMembership {
            $member->forceFill(['role' => $role])->save();
            $member->locations()->sync($locations->modelKeys());

            return $member;
        });
    }

    public function deactivate(TenantMembership $owner, TenantMembership $member): void
    {
        $this->assertCanManage($owner, $member);

        $member->forceFill(['is_active' => false])->save();
    }

    /**
     * Reactivating adds an active member again, so it counts against max_members.
     *
     * @throws PlanLimitExceededException
     */
    public function activate(TenantMembership $owner, TenantMembership $member): void
    {
        $this->assertCanManage($owner, $member);

        DB::transaction(function () use ($member): void {
            DB::table('tenants')->where('id', $this->context->id())->lockForUpdate()->first();
            $member = TenantMembership::query()->lockForUpdate()->findOrFail($member->getKey());

            if ($member->is_active) {
                return;
            }

            $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxMembers);
            $member->forceFill(['is_active' => true])->save();
        });

        $member->refresh();
    }

    /**
     * Deletes the membership and its branch assignments; the user account stays.
     */
    public function remove(TenantMembership $owner, TenantMembership $member): void
    {
        $this->assertCanManage($owner, $member);

        $member->delete();
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function assertCanManage(TenantMembership $owner, TenantMembership $member): void
    {
        if (! $this->context->check()
            || (int) $owner->tenant_id !== $this->context->id()
            || (int) $member->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The membership belongs to another tenant.');
        }

        if (! $owner->isOwner() || ! $owner->is_active) {
            throw new AuthorizationException;
        }

        if ($member->isOwner() || $member->is($owner)) {
            throw ValidationException::withMessages(['member' => __('team.errors.owner_locked')]);
        }
    }
}
