<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $assigned;

    private Location $unassigned;

    private Location $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->assigned = Location::factory()->for($this->tenant)->create();
        $this->unassigned = Location::factory()->for($this->tenant)->create();
        $this->foreign = Location::factory()->create();
    }

    private function member(string $state): User
    {
        $membership = TenantMembership::factory()->{$state}()->for($this->tenant)->create();
        $membership->locations()->attach($this->assigned);

        $this->app->make(TenantContext::class)->set($this->tenant);

        return $membership->user;
    }

    private function owner(): User
    {
        $membership = TenantMembership::factory()->for($this->tenant)->create();
        $this->app->make(TenantContext::class)->set($this->tenant);

        return $membership->user;
    }

    public function test_owner_reaches_every_location_of_the_tenant_and_nothing_else(): void
    {
        $membership = $this->owner()->membershipFor($this->tenant);

        $this->assertEqualsCanonicalizing(
            [$this->assigned->id, $this->unassigned->id],
            $membership->accessibleLocations()->pluck('id')->all(),
        );
        $this->assertTrue($membership->canAccessLocation($this->unassigned));
        $this->assertFalse($membership->canAccessLocation($this->foreign));
    }

    public function test_branch_staff_reaches_only_assigned_locations(): void
    {
        $membership = $this->member('branchStaff')->membershipFor($this->tenant);

        $this->assertSame([$this->assigned->id], $membership->accessibleLocations()->pluck('id')->all());
        $this->assertTrue($membership->canAccessLocation($this->assigned));
        $this->assertFalse($membership->canAccessLocation($this->unassigned));
        $this->assertFalse($membership->canAccessLocation($this->foreign));
    }

    public function test_owner_policy(): void
    {
        $user = $this->owner();

        $this->assertTrue($user->can('viewAny', Location::class));
        $this->assertTrue($user->can('create', Location::class));
        $this->assertTrue($user->can('update', $this->unassigned));
        $this->assertTrue($user->can('delete', $this->unassigned));
        $this->assertFalse($user->can('forceDelete', $this->unassigned));
        $this->assertFalse($user->can('view', $this->foreign));
        $this->assertFalse($user->can('update', $this->foreign));
    }

    public function test_manager_policy(): void
    {
        $user = $this->member('manager');

        $this->assertTrue($user->can('view', $this->assigned));
        $this->assertTrue($user->can('update', $this->assigned));
        $this->assertFalse($user->can('view', $this->unassigned));
        $this->assertFalse($user->can('update', $this->unassigned));
        $this->assertFalse($user->can('create', Location::class));
        $this->assertFalse($user->can('delete', $this->assigned));
    }

    public function test_branch_staff_policy(): void
    {
        $user = $this->member('branchStaff');

        $this->assertTrue($user->can('view', $this->assigned));
        $this->assertFalse($user->can('update', $this->assigned));
        $this->assertFalse($user->can('view', $this->unassigned));
        $this->assertFalse($user->can('create', Location::class));
        $this->assertFalse($user->can('delete', $this->assigned));
    }

    public function test_everything_is_denied_without_a_tenant_context(): void
    {
        $user = $this->owner();
        $this->app->make(TenantContext::class)->clear();

        $this->assertFalse($user->can('viewAny', Location::class));
        $this->assertFalse($user->can('view', $this->assigned));
        $this->assertFalse($user->can('update', $this->assigned));
    }

    public function test_inactive_membership_is_denied(): void
    {
        $membership = TenantMembership::factory()->inactive()->for($this->tenant)->create();
        $this->app->make(TenantContext::class)->set($this->tenant);

        $this->assertFalse($membership->user->can('viewAny', Location::class));
        $this->assertFalse($membership->user->can('view', $this->assigned));
    }

    public function test_owner_of_a_suspended_tenant_is_denied_even_with_a_context(): void
    {
        $user = $this->owner();
        $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

        $this->assertFalse($user->can('viewAny', Location::class));
        $this->assertFalse($user->can('view', $this->assigned));
        $this->assertFalse($user->can('update', $this->assigned));
    }

    public function test_platform_role_grants_nothing_on_tenant_locations(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->app->make(TenantContext::class)->set($this->tenant);

        $this->assertFalse($admin->can('viewAny', Location::class));
        $this->assertFalse($admin->can('view', $this->assigned));
        $this->assertFalse($admin->can('update', $this->assigned));
    }
}
