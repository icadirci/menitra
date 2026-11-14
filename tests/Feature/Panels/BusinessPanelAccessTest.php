<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\TenantStatus;
use App\Http\Middleware\ResolveTenantFromMembership;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class BusinessPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(?Tenant $tenant = null): User
    {
        $membership = TenantMembership::factory()
            ->for($tenant ?? Tenant::factory()->create())
            ->create();

        return $membership->user;
    }

    public function test_member_is_redirected_to_their_tenant_and_sees_its_dashboard(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $user = $this->owner($tenant);

        $this->actingAs($user)->get('/app')->assertRedirect('http://localhost/app/yaman-kahvesi');

        $this->actingAs($user)
            ->get('/app/yaman-kahvesi')
            ->assertOk()
            ->assertSee($tenant->getFilamentName());
    }

    public function test_member_gets_404_for_another_tenant(): void
    {
        $user = $this->owner();
        $other = Tenant::factory()->create();

        $this->actingAs($user)->get("/app/{$other->slug}")->assertNotFound();
    }

    public function test_unknown_tenant_slug_gives_404(): void
    {
        $this->actingAs($this->owner())->get('/app/does-not-exist')->assertNotFound();
    }

    public function test_inactive_membership_grants_no_access(): void
    {
        $membership = TenantMembership::factory()->inactive()->create();

        $this->actingAs($membership->user)->get('/app')->assertForbidden();
        $this->actingAs($membership->user)->get("/app/{$membership->tenant->slug}")->assertForbidden();
    }

    public function test_inactive_membership_in_one_tenant_gives_404_there_even_with_access_elsewhere(): void
    {
        $user = $this->owner();
        $inactive = TenantMembership::factory()->inactive()->for($user)->create();

        $this->actingAs($user)->get("/app/{$inactive->tenant->slug}")->assertNotFound();
    }

    public function test_member_of_a_suspended_tenant_gets_an_explicit_403_and_no_context(): void
    {
        $user = $this->owner();
        $suspended = Tenant::factory()->suspended()->create();
        TenantMembership::factory()->for($user)->for($suspended)->create();

        $this->actingAs($user)
            ->get("/app/{$suspended->slug}")
            ->assertForbidden()
            ->assertSee(__('tenancy.suspended'));

        $this->assertFalse($this->app->make(TenantContext::class)->check());
    }

    public function test_non_member_gets_404_for_a_suspended_tenant(): void
    {
        $suspended = Tenant::factory()->suspended()->create();

        $this->actingAs($this->owner())->get("/app/{$suspended->slug}")->assertNotFound();
    }

    public function test_user_whose_only_tenant_is_suspended_can_log_in_and_is_told_why(): void
    {
        $membership = TenantMembership::factory()->for(Tenant::factory()->suspended()->create())->create();
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm(['email' => $membership->user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->get('/app')->assertRedirect("http://localhost/app/{$membership->tenant->slug}");
        $this->get("/app/{$membership->tenant->slug}")->assertForbidden()->assertSee(__('tenancy.suspended'));
    }

    public function test_suspension_keeps_tenant_data_intact(): void
    {
        $tenant = Tenant::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create();

        $tenant->forceFill(['status' => TenantStatus::Suspended])->save();
        $this->actingAs($membership->user)->get("/app/{$tenant->slug}")->assertForbidden();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('tenant_user', ['id' => $membership->id, 'is_active' => true]);
    }

    public function test_soft_deleted_tenant_is_not_accessible(): void
    {
        $user = $this->owner();
        $deleted = Tenant::factory()->create();
        TenantMembership::factory()->for($user)->for($deleted)->create();
        $deleted->delete();

        $this->actingAs($user)->get("/app/{$deleted->slug}")->assertNotFound();
    }

    public function test_tenant_switcher_lists_only_tenants_with_an_active_membership(): void
    {
        $user = $this->owner($a = Tenant::factory()->create(['slug' => 'aaa-cafe']));
        TenantMembership::factory()->manager()->for($user)->for($b = Tenant::factory()->create(['slug' => 'bbb-bistro']))->create();
        // Suspended tenants are listed so their members reach the explicit 403 page.
        TenantMembership::factory()->for($user)->for($c = Tenant::factory()->suspended()->create(['slug' => 'ccc-suspended']))->create();
        TenantMembership::factory()->inactive()->for($user)->create();
        $deleted = Tenant::factory()->create(['slug' => 'ddd-deleted']);
        TenantMembership::factory()->for($user)->for($deleted)->create();
        $deleted->delete();
        Tenant::factory()->create(); // unrelated tenant

        $this->assertSame(
            [$a->id, $b->id, $c->id],
            $user->getTenants(Filament::getPanel('app'))->pluck('id')->all(),
        );
    }

    public function test_user_with_both_grants_can_use_both_panels(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->platformStaff()->create();
        TenantMembership::factory()->for($user)->for($tenant)->create();

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get("/app/{$tenant->slug}")->assertOk();
    }

    public function test_business_user_cannot_open_admin(): void
    {
        $this->actingAs($this->owner())->get('/admin')->assertForbidden();
    }

    public function test_business_user_can_log_in_through_the_app_login_page(): void
    {
        $user = $this->owner();
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_without_membership_cannot_log_in_to_app(): void
    {
        $user = User::factory()->create();
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_middleware_sets_context_only_after_verifying_the_membership(): void
    {
        $user = $this->owner($tenant = Tenant::factory()->create());
        $context = $this->app->make(TenantContext::class);
        $middleware = new ResolveTenantFromMembership($context);
        $request = Request::create("/app/{$tenant->slug}");
        $request->setUserResolver(fn () => $user);

        Filament::setTenant($tenant, isQuiet: true);
        $middleware->handle($request, fn () => response('ok'));

        $this->assertSame($tenant->id, $context->id());
    }

    public function test_middleware_refuses_a_tenant_the_user_does_not_belong_to(): void
    {
        $user = $this->owner();
        $other = Tenant::factory()->create();
        $context = $this->app->make(TenantContext::class);
        $context->set($user->memberships()->first()->tenant);
        $request = Request::create("/app/{$other->slug}");
        $request->setUserResolver(fn () => $user);

        // Simulates Filament resolving a tenant the user can't access, e.g. if
        // canAccessTenant() were ever misconfigured.
        Filament::setTenant($other, isQuiet: true);

        try {
            (new ResolveTenantFromMembership($context))->handle($request, fn () => response('ok'));
            $this->fail('Expected a 404.');
        } catch (NotFoundHttpException) {
            $this->assertFalse($context->check());
        }
    }
}
