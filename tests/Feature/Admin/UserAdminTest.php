<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminAuditAction;
use App\Enums\PlatformRole;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Models\AdminAuditEvent;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Services\Admin\UserAdminService;
use App\Services\Team\TeamInvitationService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * /admin user management (launch plan Faz 8): policy matrix, platform users,
 * platform roles, verification mails, deactivation, audit log.
 */
class UserAdminTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    private function asSuperAdmin(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel('admin');
    }

    private function service(): UserAdminService
    {
        return app(UserAdminService::class);
    }

    private function denied(callable $callback): void
    {
        try {
            $callback();
            $this->fail('An AuthorizationException was expected.');
        } catch (AuthorizationException) {
        }
    }

    public function test_every_platform_role_lists_and_views_users_business_users_do_not(): void
    {
        $owner = TenantMembership::factory()->for(Tenant::factory()->create(['slug' => 'konak']))->create()->user;

        $this->actingAs($this->superAdmin)->get('/admin/users')->assertOk()->assertSee($owner->email);
        $this->actingAs(User::factory()->platformStaff()->create())->get("/admin/users/{$owner->id}")->assertOk();
        $this->actingAs($owner)->get('/admin/users')->assertForbidden();

        $this->asSuperAdmin();
        Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => ViewUser::class])
            ->assertOk()
            ->assertSee('konak');
    }

    public function test_platform_staff_can_only_resend_verification(): void
    {
        $staff = User::factory()->platformStaff()->create();
        $user = User::factory()->unverified()->create();

        $this->actingAs($staff);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->assertActionVisible('resendVerification')
            ->assertActionHidden('impersonate')
            ->assertActionHidden('changePlatformRole')
            ->assertActionHidden('deactivate');
        Livewire::test(ListUsers::class)->assertActionHidden('createPlatformUser');

        $this->denied(fn () => $this->service()->createPlatformUser($staff, 'Ali', 'ali@example.com', PlatformRole::PlatformStaff));
        $this->denied(fn () => $this->service()->changePlatformRole($staff, $user, PlatformRole::SuperAdmin));
        $this->denied(fn () => $this->service()->deactivate($staff, $user));
        $this->assertNull($user->fresh()->platform_role);
        $this->assertFalse($user->fresh()->isDeactivated());
    }

    public function test_a_super_admin_creates_a_platform_user_who_gets_a_reset_link(): void
    {
        $this->asSuperAdmin();

        Livewire::test(ListUsers::class)
            ->callAction('createPlatformUser', ['name' => 'Destek Ekibi', 'email' => 'Destek@Example.com', 'role' => 'platform_staff'])
            ->assertHasNoActionErrors();

        $user = User::query()->where('email', 'destek@example.com')->sole();
        $this->assertSame(PlatformRole::PlatformStaff, $user->platform_role);
        $this->assertFalse($user->hasTwoFactorEnabled());
        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $event = AdminAuditEvent::query()->sole();
        $this->assertSame(AdminAuditAction::PlatformUserCreated, $event->action);
        $this->assertSame(['type' => 'user', 'id' => $user->id], ['type' => $event->subject_type, 'id' => $event->subject_id]);
        $this->assertSame(['role' => 'platform_staff'], $event->meta);

        $this->expectException(ValidationException::class);
        $this->service()->createPlatformUser($this->superAdmin, 'Tekrar', 'destek@example.com', 'platform_staff');
    }

    public function test_platform_roles_are_changed_by_super_admins_only_and_never_on_themselves(): void
    {
        $user = User::factory()->create();

        $this->service()->changePlatformRole($this->superAdmin, $user, PlatformRole::PlatformStaff);
        $this->assertSame(PlatformRole::PlatformStaff, $user->fresh()->platform_role);

        $this->service()->changePlatformRole($this->superAdmin, $user->fresh(), null);
        $this->assertNull($user->fresh()->platform_role);

        $this->denied(fn () => $this->service()->changePlatformRole($this->superAdmin, $this->superAdmin, null));
        $this->assertSame(PlatformRole::SuperAdmin, $this->superAdmin->fresh()->platform_role);

        $this->assertEquals(
            [['from' => null, 'to' => 'platform_staff'], ['from' => 'platform_staff', 'to' => null]],
            AdminAuditEvent::query()->orderBy('id')->pluck('meta')->all(),
        );
    }

    public function test_verification_mails_are_resent_for_unverified_users_once_per_minute(): void
    {
        $staff = User::factory()->platformStaff()->create();
        $user = User::factory()->unverified()->create();

        $this->service()->resendVerification($staff, $user);
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);

        try {
            $this->service()->resendVerification($staff, $user);
            $this->fail('The second resend within a minute must be refused.');
        } catch (ValidationException) {
        }

        $this->travel(61)->seconds();
        $this->service()->resendVerification($staff, $user);
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 2);

        $this->denied(fn () => $this->service()->resendVerification($staff, User::factory()->create()));
        $this->assertSame(2, AdminAuditEvent::query()->where('action', AdminAuditAction::VerificationResent)->count());
    }

    public function test_a_deactivated_user_loses_access_immediately_and_can_be_reactivated(): void
    {
        $tenant = $this->subscribedTenant('konak');
        $membership = TenantMembership::factory()->for($tenant)->create();
        $user = $membership->user;

        $this->actingAs($user)->get('/app/konak')->assertOk();

        $this->service()->deactivate($this->superAdmin, $user);

        // The open session ends on the next request.
        $this->get('/app/konak')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('app')));
        $this->assertSame(1, $membership->fresh()->is_active ? 1 : 0, 'Memberships are kept.');

        $this->service()->reactivate($this->superAdmin, $user->fresh());
        $this->actingAs($user->fresh())->get('/app/konak')->assertOk();

        $this->assertSame(
            [AdminAuditAction::UserDeactivated, AdminAuditAction::UserReactivated],
            AdminAuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_nobody_deactivates_themselves_and_a_deactivated_admin_loses_admin(): void
    {
        $this->denied(fn () => $this->service()->deactivate($this->superAdmin, $this->superAdmin));

        $other = User::factory()->superAdmin()->create();
        $this->service()->deactivate($this->superAdmin, $other);

        $this->actingAs($other->fresh())->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_accept_an_invitation_with_its_password(): void
    {
        $tenant = $this->subscribedTenant('konak');
        $location = $this->location($tenant, 'moda', true);
        $owner = TenantMembership::factory()->for($tenant)->create();
        $invited = User::factory()->create(['email' => 'ayse@example.com']);
        $invitation = $this->inTenant($tenant, fn () => app(TeamInvitationService::class)->invite($owner, 'ayse@example.com', 'branch_staff', [$location->id]));

        $this->service()->deactivate($this->superAdmin, $invited);

        $this->expectException(ValidationException::class);
        app(TeamInvitationService::class)->acceptWithPassword($invitation, 'password');
    }
}
