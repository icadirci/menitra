<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\PlanFeature;
use App\Enums\TenantRole;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Filament\App\Resources\Team\Pages\ListTeamInvitations;
use App\Filament\App\Resources\Team\Pages\ListTeamMembers;
use App\Mail\TeamInvitationMail;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\FeatureGate;
use App\Services\Team\InvitationTokenResolver;
use App\Services\Team\TeamInvitationService;
use App\Services\Team\TeamService;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    private TenantMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->tenant = $this->subscribedTenant('konak', ['max_members' => 3]);
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        $this->owner = TenantMembership::factory()->for($this->tenant)->create();
    }

    private function member(string $role, Location ...$locations): TenantMembership
    {
        $membership = TenantMembership::factory()->for($this->tenant)->{$role}()->create();
        $membership->locations()->attach(array_map(fn (Location $location) => $location->getKey(), $locations));

        return $membership;
    }

    /**
     * @param  list<int>|null  $locations
     */
    private function invite(string $email, TenantRole $role = TenantRole::BranchStaff, ?array $locations = null, ?TenantMembership $by = null): TenantInvitation
    {
        return $this->inTenant($this->tenant, fn (): TenantInvitation => app(TeamInvitationService::class)
            ->invite($by ?? $this->owner, $email, $role, $locations ?? [$this->moda->id]));
    }

    private function lastToken(): string
    {
        $url = null;
        Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) use (&$url): bool {
            $url = $mail->url;

            return true;
        });

        return basename((string) parse_url((string) $url, PHP_URL_PATH));
    }

    private function livewire(User $user, string $component): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        app(TenantContext::class)->set($this->tenant);

        return Livewire::test($component);
    }

    public function test_only_the_owner_reaches_the_team_pages(): void
    {
        foreach (['team', 'team-invitations'] as $path) {
            $this->actingAs($this->owner->user)->get("/app/konak/{$path}")->assertOk();
            $this->actingAs($this->member('manager', $this->moda)->user)->get("/app/konak/{$path}")->assertForbidden();
            $this->actingAs($this->member('branchStaff', $this->moda)->user)->get("/app/konak/{$path}")->assertForbidden();
        }
    }

    public function test_owner_invites_a_member_from_the_panel(): void
    {
        $this->livewire($this->owner->user, ListTeamInvitations::class)
            ->callAction('invite', data: [
                'email' => 'Selin@Example.com',
                'role' => TenantRole::Manager->value,
                'location_ids' => [(string) $this->moda->id, (string) $this->cihangir->id],
            ])
            ->assertHasNoActionErrors();

        $invitation = $this->inTenant($this->tenant, fn () => TenantInvitation::query()->with('locations')->sole());
        $this->assertSame('selin@example.com', $invitation->email);
        $this->assertSame(TenantRole::Manager, $invitation->role);
        $this->assertSame($this->owner->id, $invitation->invited_by_membership_id);
        $this->assertEqualsCanonicalizing([$this->moda->id, $this->cihangir->id], $invitation->locations->modelKeys());
        $this->assertTrue($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));

        // Only the hash is stored; the raw token exists only in the mail.
        $token = $this->lastToken();
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('tenant_invitations', ['token_hash' => $token]);
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());

        Mail::assertQueued(TeamInvitationMail::class, fn (TeamInvitationMail $mail): bool => $mail->hasTo('selin@example.com')
            && $mail->locale === 'tr'
            && $mail->afterCommit === true);
    }

    public function test_invitations_validate_role_branches_and_existing_members(): void
    {
        $other = $this->subscribedTenant('baska');
        $foreign = $this->location($other, 'merkez');
        $manager = $this->member('manager', $this->moda);

        $cases = [
            'owner role' => fn () => $this->invite('a@example.com', TenantRole::Owner),
            'no branch' => fn () => $this->invite('a@example.com', locations: []),
            'foreign branch' => fn () => $this->invite('a@example.com', locations: [$this->moda->id, $foreign->id]),
            'unknown branch' => fn () => $this->invite('a@example.com', locations: [999999]),
            'invalid email' => fn () => $this->invite('not-an-email'),
            'already member' => fn () => $this->invite(strtoupper($manager->user->email)),
        ];

        foreach ($cases as $case => $call) {
            try {
                $call();
                $this->fail("Expected a validation error: {$case}");
            } catch (ValidationException) {
                // expected
            }
        }

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, TenantInvitation::query()->count()));
        Mail::assertNothingQueued();
    }

    public function test_only_one_open_invitation_per_email(): void
    {
        $first = $this->invite('selin@example.com');

        $this->expectValidationError(fn () => $this->invite('SELIN@example.com'), 'email');

        // Once expired, a new invitation replaces it.
        $this->travel(8)->days();
        $second = $this->invite('selin@example.com');

        $this->assertNotNull($first->refresh()->revoked_at);
        $this->assertTrue($second->isPending());

        // The database also allows a single open row per email.
        $this->expectException(QueryException::class);
        DB::table('tenant_invitations')->insert([
            'tenant_id' => $this->tenant->id, 'email' => 'selin@example.com', 'role' => 'manager', 'token_hash' => str_repeat('a', 64),
            'locale' => 'tr', 'expires_at' => now()->addDay(), 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_max_members_counts_active_members_and_pending_invitations(): void
    {
        $gate = fn (): int => $this->inTenant($this->tenant, fn (): int => app(FeatureGate::class)->usage($this->tenant, PlanFeature::MaxMembers));

        // Another tenant's members and invitations never count here.
        $other = $this->subscribedTenant('baska');
        $otherLocation = $this->location($other, 'merkez', true);
        $otherOwner = TenantMembership::factory()->for($other)->create();
        $this->inTenant($other, fn () => app(TeamInvitationService::class)->invite($otherOwner, 'x@example.com', TenantRole::Manager, [$otherLocation->id]));

        $this->assertSame(1, $gate());
        $this->member('manager', $this->moda);
        $first = $this->invite('one@example.com');
        $this->assertSame(3, $gate());

        try {
            $this->invite('two@example.com');
            $this->fail('The member limit must apply.');
        } catch (PlanLimitExceededException $exception) {
            $this->assertSame(PlanFeature::MaxMembers, $exception->feature);
        }

        // Inactive members and revoked or expired invitations don't count.
        $this->inTenant($this->tenant, fn () => app(TeamInvitationService::class)->revoke($this->owner, $first));
        $this->assertSame(2, $gate());
        $this->invite('two@example.com');
        $this->travel(8)->days();
        $this->assertSame(2, $gate());

        // Resending an expired invitation makes it count again, within the limit.
        $this->member('branchStaff', $this->moda);
        $expired = $this->inTenant($this->tenant, fn () => TenantInvitation::query()->open()->sole());
        $this->expectException(PlanLimitExceededException::class);
        $this->inTenant($this->tenant, fn () => app(TeamInvitationService::class)->resend($this->owner, $expired));
    }

    public function test_tenant_without_the_feature_cannot_invite(): void
    {
        $tenant = $this->subscribedTenant('kucuk', ['max_members' => 0]);
        $location = $this->location($tenant, 'merkez', true);
        $owner = TenantMembership::factory()->for($tenant)->create();

        $this->expectException(PlanLimitExceededException::class);
        $this->inTenant($tenant, fn () => app(TeamInvitationService::class)->invite($owner, 'a@example.com', TenantRole::Manager, [$location->id]));
    }

    public function test_only_owners_invite_and_manage_even_through_the_services(): void
    {
        $invitation = $this->invite('selin@example.com');
        $manager = $this->member('manager', $this->moda, $this->cihangir);
        $staff = $this->member('branchStaff', $this->moda);

        $calls = [
            fn () => $this->invite('x@example.com', by: $manager),
            fn () => app(TeamInvitationService::class)->revoke($manager, $invitation),
            fn () => app(TeamInvitationService::class)->resend($manager, $invitation),
            fn () => app(TeamService::class)->update($manager, $staff, TenantRole::Manager, [$this->moda->id]),
            fn () => app(TeamService::class)->deactivate($manager, $staff),
            fn () => app(TeamService::class)->remove($manager, $staff),
        ];

        foreach ($calls as $call) {
            try {
                $this->inTenant($this->tenant, $call);
                $this->fail('Only owners may manage the team.');
            } catch (AuthorizationException) {
                // expected
            }
        }

        $this->livewire($manager->user, ListTeamMembers::class)->assertForbidden();
    }

    public function test_services_refuse_another_tenants_records(): void
    {
        $other = $this->subscribedTenant('baska');
        $otherOwner = TenantMembership::factory()->for($other)->create();
        $staff = $this->member('branchStaff', $this->moda);

        $this->expectException(CrossTenantWriteException::class);
        $this->inTenant($other, fn () => app(TeamService::class)->deactivate($otherOwner, $staff));
    }

    public function test_owner_changes_role_and_branches(): void
    {
        $staff = $this->member('branchStaff', $this->moda);

        $this->livewire($this->owner->user, ListTeamMembers::class)
            ->callTableAction('editMember', $staff, data: [
                'role' => TenantRole::Manager->value,
                'location_ids' => [(string) $this->cihangir->id],
            ])
            ->assertHasNoTableActionErrors();

        $staff->refresh();
        $this->assertSame(TenantRole::Manager, $staff->role);
        $this->assertSame([$this->cihangir->id], $staff->assignedLocationIds()->pluck('location_id')->map(fn ($id) => (int) $id)->all());

        // Never owner, always at least one branch.
        $this->expectValidationError(fn () => app(TeamService::class)->update($this->owner, $staff, TenantRole::Owner, [$this->moda->id]), 'role');
        $this->expectValidationError(fn () => app(TeamService::class)->update($this->owner, $staff, TenantRole::Manager, []), 'location_ids');
    }

    public function test_another_tenants_branch_can_never_be_assigned(): void
    {
        $other = $this->subscribedTenant('baska');
        $foreign = $this->location($other, 'merkez');
        $staff = $this->member('branchStaff', $this->moda);
        $invitation = $this->invite('selin@example.com');

        $this->expectValidationError(fn () => app(TeamService::class)->update($this->owner, $staff, TenantRole::Manager, [$foreign->id]), 'location_ids');

        // The composite foreign keys reject it even without the service.
        foreach ([
            fn () => DB::table('location_user')->insert(['tenant_id' => $this->tenant->id, 'tenant_user_id' => $staff->id, 'location_id' => $foreign->id]),
            fn () => DB::table('tenant_invitation_location')->insert(['tenant_id' => $this->tenant->id, 'tenant_invitation_id' => $invitation->id, 'location_id' => $foreign->id]),
            fn () => DB::table('tenant_invitation_location')->insert(['tenant_id' => $other->id, 'tenant_invitation_id' => $invitation->id, 'location_id' => $foreign->id]),
        ] as $insert) {
            try {
                DB::transaction($insert);
                $this->fail('The database must reject a cross-tenant branch.');
            } catch (QueryException) {
                // expected
            }
        }
    }

    public function test_the_owner_membership_is_locked(): void
    {
        $this->livewire($this->owner->user, ListTeamMembers::class)
            ->assertTableActionHidden('editMember', $this->owner)
            ->assertTableActionHidden('toggleActive', $this->owner)
            ->assertTableActionHidden('remove', $this->owner);

        foreach (['deactivate', 'remove'] as $method) {
            $this->expectValidationError(fn () => app(TeamService::class)->{$method}($this->owner, $this->owner), 'member');
        }

        $this->assertTrue($this->owner->refresh()->is_active);
    }

    public function test_deactivated_member_loses_access_immediately_and_reactivation_counts(): void
    {
        $staff = $this->member('branchStaff', $this->moda);
        $this->actingAs($staff->user)->get('/app/konak')->assertOk();

        $this->livewire($this->owner->user, ListTeamMembers::class)->callTableAction('toggleActive', $staff);
        $this->assertFalse($staff->refresh()->is_active);

        // With no other membership the panel itself is refused.
        auth()->forgetGuards();
        $this->actingAs($staff->user->fresh())->get('/app/konak')->assertForbidden();

        // Reactivating is limited by max_members (owner + 2 pending = 3).
        $this->invite('one@example.com');
        $this->invite('two@example.com');
        $this->expectException(PlanLimitExceededException::class);
        $this->inTenant($this->tenant, fn () => app(TeamService::class)->activate($this->owner, $staff));
    }

    public function test_owner_removes_a_member(): void
    {
        $staff = $this->member('branchStaff', $this->moda);
        $user = $staff->user;

        $this->livewire($this->owner->user, ListTeamMembers::class)->callTableAction('remove', $staff);

        $this->assertModelMissing($staff);
        $this->assertDatabaseMissing('location_user', ['tenant_user_id' => $staff->id]);
        $this->assertModelExists($user);
    }

    public function test_resend_rotates_the_token_and_revoke_closes_it(): void
    {
        $invitation = $this->invite('selin@example.com');
        $firstToken = $this->lastToken();
        $resolver = app(InvitationTokenResolver::class);
        $this->assertTrue($invitation->is($resolver->resolve($firstToken)));

        // Cooldown against mail flooding.
        $this->expectValidationError(fn () => app(TeamInvitationService::class)->resend($this->owner, $invitation), 'invitation');

        $this->travel(2)->minutes();
        Mail::fake();
        $this->livewire($this->owner->user, ListTeamInvitations::class)->callTableAction('resend', $invitation);
        $secondToken = $this->lastToken();

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertNull($resolver->resolve($firstToken));
        $this->assertTrue($invitation->is($resolver->resolve($secondToken)));

        $this->livewire($this->owner->user, ListTeamInvitations::class)->callTableAction('revoke', $invitation);
        $this->assertNotNull($invitation->refresh()->revoked_at);
        $this->assertNull($resolver->resolve($secondToken));
    }

    public function test_invitation_list_shows_only_this_tenants_open_invitations(): void
    {
        $open = $this->invite('open@example.com');
        $revoked = $this->invite('revoked@example.com');
        $this->inTenant($this->tenant, fn () => app(TeamInvitationService::class)->revoke($this->owner, $revoked));

        $other = $this->subscribedTenant('baska');
        $otherLocation = $this->location($other, 'merkez', true);
        $otherOwner = TenantMembership::factory()->for($other)->create();
        $foreign = $this->inTenant($other, fn () => app(TeamInvitationService::class)->invite($otherOwner, 'foreign@example.com', TenantRole::Manager, [$otherLocation->id]));

        $this->livewire($this->owner->user, ListTeamInvitations::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$revoked, $foreign]);

        $otherMember = TenantMembership::factory()->for($other)->manager()->create();
        $this->livewire($this->owner->user, ListTeamMembers::class)
            ->assertCanSeeTableRecords([$this->owner])
            ->assertCanNotSeeTableRecords([$otherOwner, $otherMember]);
    }

    private function expectValidationError(callable $call, string $key): void
    {
        try {
            $this->inTenant($this->tenant, $call);
            $this->fail("Expected a validation error on [{$key}].");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }
}
