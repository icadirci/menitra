<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminAuditAction;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Models\AdminAuditEvent;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Admin\Impersonation;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * Limited impersonation (launch plan Faz 8, security.md § Impersonation).
 */
class ImpersonationTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak');
        $this->location($this->tenant, 'moda', true);
        $this->owner = TenantMembership::factory()->for($this->tenant)->create()->user;
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    /**
     * Starts an impersonation the way the admin action does (in a request
     * with a session).
     */
    private function startImpersonation(?User $target = null): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewUser::class, ['record' => ($target ?? $this->owner)->getRouteKey()])
            ->callAction('impersonate')
            ->assertHasNoActionErrors()
            ->assertRedirect();
    }

    private function denied(callable $callback): void
    {
        try {
            $callback();
            $this->fail('The impersonation must be refused.');
        } catch (AuthorizationException|ValidationException) {
        }
    }

    public function test_a_super_admin_views_the_business_panel_as_the_user(): void
    {
        $this->startImpersonation();

        $this->assertAuthenticatedAs($this->owner);
        $state = session(Impersonation::SESSION_KEY);
        $this->assertSame($this->superAdmin->id, $state['impersonator_id']);
        $this->assertSame($this->owner->id, $state['user_id']);

        $this->get('/app/konak')
            ->assertOk()
            ->assertSee(__('admin.impersonation.banner', ['name' => $this->owner->name, 'email' => $this->owner->email]))
            ->assertSee(route('impersonation.leave'));

        $event = AdminAuditEvent::query()->sole();
        $this->assertSame(AdminAuditAction::ImpersonationStarted, $event->action);
        $this->assertSame($this->superAdmin->id, $event->actor_user_id);
        $this->assertSame($this->owner->id, $event->subject_id);
    }

    public function test_only_super_admins_impersonate_only_business_users(): void
    {
        $service = app(Impersonation::class);
        $staff = User::factory()->platformStaff()->create();

        $this->denied(fn () => $service->start($staff, $this->owner));
        $this->denied(fn () => $service->start($this->superAdmin, $staff));
        $this->denied(fn () => $service->start($this->superAdmin, User::factory()->superAdmin()->create()));
        $this->denied(fn () => $service->start($this->superAdmin, $this->superAdmin));
        $this->denied(fn () => $service->start($this->superAdmin, User::factory()->create()), 'no membership');
        $this->denied(fn () => $service->start($this->superAdmin, User::factory()->create(['deactivated_at' => now()])));

        $this->actingAs($staff);
        Filament::setCurrentPanel('admin');
        Livewire::test(ViewUser::class, ['record' => $this->owner->getRouteKey()])->assertActionHidden('impersonate');

        $this->assertSame(0, AdminAuditEvent::query()->count());
    }

    public function test_admin_profile_and_invitation_acceptance_are_blocked_while_impersonating(): void
    {
        $this->startImpersonation();

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/app/profile')->assertForbidden();
        $this->post('/invitations/'.str_repeat('a', 43))->assertForbidden();

        // Password reset routes are guest-only.
        $this->get('/forgot-password')->assertRedirect('/app');

        // Two-factor writes are refused by the model itself (inside a request,
        // where the session is started).
        session()->start();
        $this->assertTrue(app(Impersonation::class)->isActive());
        try {
            $this->owner->saveAppAuthenticationSecret('SECRETSECRETSECRET');
            $this->fail('Two-factor changes must be refused while impersonating.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertNull($this->owner->fresh()->app_authentication_secret);

        // Nested impersonation is refused.
        $this->denied(fn () => app(Impersonation::class)->start($this->superAdmin, $this->owner));
    }

    public function test_leaving_restores_the_admin_and_is_audited(): void
    {
        $this->startImpersonation();
        $this->get('/app/konak')->assertOk();
        $sessionId = session()->getId();

        $this->post(route('impersonation.leave'))
            ->assertRedirect("http://localhost/admin/users/{$this->owner->id}");

        $this->assertAuthenticatedAs($this->superAdmin);
        $this->assertNull(session(Impersonation::SESSION_KEY));
        $this->assertNotSame($sessionId, session()->getId(), 'The session id is regenerated.');
        $this->get('/admin')->assertOk();

        $event = AdminAuditEvent::query()->latest('id')->first();
        $this->assertSame(AdminAuditAction::ImpersonationEnded, $event->action);
        $this->assertSame($this->superAdmin->id, $event->actor_user_id);
        $this->assertSame('left', $event->meta['reason']);

        // Without an impersonation, the route doesn't exist.
        $this->post(route('impersonation.leave'))->assertNotFound();
    }

    public function test_the_impersonation_ends_automatically_after_sixty_minutes(): void
    {
        $this->startImpersonation();

        $this->travel(59)->minutes();
        $this->get('/app/konak')->assertOk();
        $this->assertAuthenticatedAs($this->owner);

        $this->travel(2)->minutes();
        $this->get('/app/konak')->assertRedirect('/admin');

        $this->assertAuthenticatedAs($this->superAdmin);
        $this->assertNull(session(Impersonation::SESSION_KEY));
        $this->assertSame('expired', AdminAuditEvent::query()->latest('id')->first()->meta['reason']);
    }

    public function test_logging_out_ends_the_impersonation_with_an_audit_entry(): void
    {
        $this->startImpersonation();

        $this->post('/logout');

        $this->assertGuest();
        $event = AdminAuditEvent::query()->latest('id')->first();
        $this->assertSame(AdminAuditAction::ImpersonationEnded, $event->action);
        $this->assertSame('logout', $event->meta['reason']);
    }

    public function test_an_admin_who_lost_the_role_is_signed_out_instead_of_restored(): void
    {
        $this->startImpersonation();
        $this->superAdmin->forceFill(['platform_role' => null])->save();

        $this->post(route('impersonation.leave'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_impersonated_user_gets_no_extra_permission(): void
    {
        $staff = TenantMembership::factory()->for($this->tenant)->branchStaff()->create()->user;
        $other = $this->subscribedTenant('baska');

        $this->startImpersonation($staff);

        $this->get('/app/baska')->assertNotFound();
        $this->get('/app/konak/team')->assertForbidden();
        $this->assertNotNull($other);
    }
}
