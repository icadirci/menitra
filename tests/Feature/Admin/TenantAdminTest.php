<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminAuditAction;
use App\Enums\TenantStatus;
use App\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use App\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use App\Filament\Admin\Resources\Tenants\RelationManagers\LocationsRelationManager;
use App\Filament\Admin\Resources\Tenants\RelationManagers\MembersRelationManager;
use App\Filament\Admin\Resources\Tenants\RelationManagers\SubscriptionEventsRelationManager;
use App\Models\AdminAuditEvent;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantSlugRedirect;
use App\Models\User;
use App\Services\Admin\TenantAdminService;
use App\Services\Menu\MenuCacheKeys;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * /admin tenant management (launch plan Faz 8): policy matrix, suspend,
 * slug change with 301 redirects and reservation, soft delete / restore,
 * audit log.
 */
class TenantAdminTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Location $moda;

    private Menu $menu;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak');
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->menu = $this->menu($this->tenant, 'menu', [$this->moda]);
        $this->category($this->menu, 'Kahveler');
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    private function asSuperAdmin(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel('admin');
    }

    private function service(): TenantAdminService
    {
        return app(TenantAdminService::class);
    }

    public function test_every_platform_role_lists_and_views_tenants_business_users_do_not(): void
    {
        $owner = TenantMembership::factory()->for($this->tenant)->create()->user;

        $this->actingAs($this->superAdmin)->get('/admin/tenants')->assertOk()->assertSee('konak');
        $this->actingAs($this->superAdmin)->get("/admin/tenants/{$this->tenant->id}")->assertOk();
        $this->actingAs(User::factory()->platformStaff()->create())->get('/admin/tenants')->assertOk();
        $this->actingAs(User::factory()->platformStaff()->create())->get("/admin/tenants/{$this->tenant->id}")->assertOk();
        $this->actingAs($owner)->get('/admin/tenants')->assertForbidden();
    }

    public function test_the_list_shows_owner_and_subscription_and_tenants_without_members(): void
    {
        $owner = TenantMembership::factory()->for($this->tenant)->create()->user;
        $memberless = Tenant::factory()->create(['slug' => 'vitrin']);
        $this->asSuperAdmin();

        Livewire::test(ListTenants::class)
            ->assertCanSeeTableRecords([$this->tenant, $memberless])
            ->assertSee($owner->email)
            ->assertSee(__('plans.statuses.trialing'));
    }

    public function test_the_detail_page_shows_members_branches_and_subscription_history(): void
    {
        $owner = TenantMembership::factory()->for($this->tenant)->create();
        $other = $this->subscribedTenant('baska');
        $this->location($other, 'kadikoy');
        $this->asSuperAdmin();

        Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])->assertOk();

        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $this->tenant, 'pageClass' => ViewTenant::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$owner]);

        Livewire::test(LocationsRelationManager::class, ['ownerRecord' => $this->tenant, 'pageClass' => ViewTenant::class])
            ->assertOk()
            ->assertSee('moda')
            ->assertDontSee('kadikoy');

        Livewire::test(SubscriptionEventsRelationManager::class, ['ownerRecord' => $this->tenant, 'pageClass' => ViewTenant::class])
            ->assertOk()
            ->assertSee(__('admin.subscription_events.trial_started'))
            ->assertCountTableRecords(1);
    }

    public function test_platform_staff_sees_no_actions_and_cannot_run_them(): void
    {
        $staff = User::factory()->platformStaff()->create();
        $this->actingAs($staff);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
            ->assertActionHidden('suspend')
            ->assertActionHidden('changeSlug')
            ->assertActionHidden('delete');

        foreach ([
            fn () => $this->service()->suspend($staff, $this->tenant),
            fn () => $this->service()->changeSlug($staff, $this->tenant, 'yeni-konak'),
            fn () => $this->service()->delete($staff, $this->tenant),
        ] as $call) {
            try {
                $call();
                $this->fail('Platform staff must not change tenants.');
            } catch (AuthorizationException) {
            }
        }

        $this->assertSame(TenantStatus::Active, $this->tenant->fresh()->status);
        $this->assertSame(0, AdminAuditEvent::query()->count());
    }

    public function test_suspend_and_unsuspend_are_audited_and_take_the_menu_offline(): void
    {
        $member = TenantMembership::factory()->for($this->tenant)->create()->user;
        $this->get('http://localhost/m/konak/moda/menu')->assertOk();
        $this->assertTrue(Cache::has(MenuCacheKeys::slug('konak')));
        $this->asSuperAdmin();

        Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
            ->callAction('suspend', ['reason' => 'Kötüye kullanım şikayeti'])
            ->assertHasNoActionErrors();

        $this->assertSame(TenantStatus::Suspended, $this->tenant->fresh()->status);
        $this->assertFalse(Cache::has(MenuCacheKeys::slug('konak')), 'Resolution cache cleared.');
        $this->get('http://localhost/m/konak/moda/menu')->assertStatus(503);
        $this->actingAs($member)->get('/app/konak')->assertForbidden();

        $event = AdminAuditEvent::query()->sole();
        $this->assertSame(AdminAuditAction::TenantSuspended, $event->action);
        $this->assertSame($this->superAdmin->id, $event->actor_user_id);
        $this->assertSame($this->tenant->id, $event->tenant_id);
        $this->assertSame(['subject' => 'tenant', 'id' => $this->tenant->id], ['subject' => $event->subject_type, 'id' => $event->subject_id]);
        $this->assertSame(['reason' => 'Kötüye kullanım şikayeti'], $event->meta);

        $this->service()->unsuspend($this->superAdmin, $this->tenant->fresh());

        $this->assertSame(TenantStatus::Active, $this->tenant->fresh()->status);
        $this->get('http://localhost/m/konak/moda/menu')->assertOk();
        $this->assertSame(AdminAuditAction::TenantUnsuspended, AdminAuditEvent::query()->latest('id')->first()->action);
    }

    public function test_a_slug_change_redirects_old_urls_with_301_and_clears_the_cache(): void
    {
        $this->get('http://localhost/m/konak/moda/menu')->assertOk();
        $this->get('http://konak.localhost/moda/menu')->assertOk();

        // Through the service: Livewire test calls would reuse the tenant host of
        // the last request (the panel endpoints are central-only).
        $this->service()->changeSlug($this->superAdmin, $this->tenant, 'konak-evi');

        $this->assertSame('konak-evi', $this->tenant->fresh()->slug);
        $this->assertFalse(Cache::has(MenuCacheKeys::slug('konak')));
        $this->assertFalse(Cache::has(MenuCacheKeys::host('konak.localhost')));

        $this->get('http://localhost/m/konak/moda/menu?lang=en')
            ->assertStatus(301)
            ->assertRedirect('http://localhost/m/konak-evi/moda/menu?lang=en');
        $this->get('http://localhost/m/konak')->assertStatus(301)->assertRedirect('http://localhost/m/konak-evi');
        $this->get('http://konak.localhost/moda/menu')->assertStatus(301)->assertRedirect('http://konak-evi.localhost/moda/menu');
        $this->get('http://konak.localhost/moda')->assertStatus(301)->assertRedirect('http://konak-evi.localhost/moda');
        $this->get('http://konak.localhost/')->assertStatus(301)->assertRedirect('http://konak-evi.localhost/');
        $this->get('http://konak-evi.localhost/moda/menu')->assertOk();
        $this->get('http://localhost/m/bilinmeyen')->assertNotFound();

        $event = AdminAuditEvent::query()->sole();
        $this->assertSame(AdminAuditAction::TenantSlugChanged, $event->action);
        $this->assertEquals(['from' => 'konak', 'to' => 'konak-evi'], $event->meta); // jsonb doesn't keep key order
    }

    public function test_a_former_slug_stays_reserved_for_its_tenant(): void
    {
        $this->asSuperAdmin();
        Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
            ->callAction('changeSlug', ['slug' => 'konak-evi'])
            ->assertHasNoActionErrors();
        $this->assertSame('konak-evi', $this->tenant->fresh()->slug);
        auth()->logout();
        $other = Tenant::factory()->create(['slug' => 'baska']);

        try {
            $this->service()->changeSlug($this->superAdmin, $other, 'konak');
            $this->fail('A former slug of another tenant must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }

        // Public registration can't take it either.
        $this->post('/register', [
            'name' => 'Ayşe', 'email' => 'ayse@example.com', 'password' => 'Str0ng!Passw0rd#', 'password_confirmation' => 'Str0ng!Passw0rd#',
            'business_name' => 'Konak', 'slug' => 'konak', 'terms' => '1',
        ])->assertSessionHasErrors('slug');

        // Its own tenant can go back: the redirect row is removed, a new one kept.
        $this->service()->changeSlug($this->superAdmin, $this->tenant->fresh(), 'konak');
        $this->assertSame(['konak-evi'], TenantSlugRedirect::query()->where('tenant_id', $this->tenant->id)->pluck('slug')->all());
        $this->get('http://localhost/m/konak/moda/menu')->assertOk();
        $this->get('http://localhost/m/konak-evi/moda/menu')->assertRedirect('http://localhost/m/konak/moda/menu');
    }

    public function test_chained_slug_changes_redirect_to_the_current_slug(): void
    {
        $this->service()->changeSlug($this->superAdmin, $this->tenant, 'konak-iki');
        $this->service()->changeSlug($this->superAdmin, $this->tenant->fresh(), 'konak-uc');

        $this->get('http://localhost/m/konak/moda')->assertRedirect('http://localhost/m/konak-uc/moda');
        $this->get('http://localhost/m/konak-iki/moda')->assertRedirect('http://localhost/m/konak-uc/moda');
    }

    public function test_invalid_slugs_are_refused(): void
    {
        Tenant::factory()->create(['slug' => 'dolu']);

        // 'Konak' is lowercased to the current slug, so it is "unchanged".
        foreach (['Konak', 'ko', 'konak--evi', '-konak', 'admin', 'dolu', 'konak', str_repeat('a', 41)] as $slug) {
            $this->expectsValidation(fn () => $this->service()->changeSlug($this->superAdmin, $this->tenant->fresh(), $slug));
        }

        $this->assertSame('konak', $this->tenant->fresh()->slug);
        $this->assertSame(0, TenantSlugRedirect::query()->count());
    }

    public function test_qr_codes_follow_a_slug_change(): void
    {
        $qr = $this->inTenant($this->tenant, fn () => $this->moda->qrCodes()->create(['menu_id' => $this->menu->getKey()]));
        $this->get('http://localhost/q/'.$qr->code)->assertRedirect('http://konak.localhost/moda/menu');

        $this->service()->changeSlug($this->superAdmin, $this->tenant, 'konak-evi');

        $this->get('http://localhost/q/'.$qr->code)->assertStatus(302)->assertRedirect('http://konak-evi.localhost/moda/menu');
    }

    public function test_soft_delete_and_restore_are_super_admin_only_and_audited(): void
    {
        $member = TenantMembership::factory()->for($this->tenant)->create()->user;
        $this->service()->changeSlug($this->superAdmin, $this->tenant, 'konak-evi');
        $this->asSuperAdmin();

        Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
            ->assertActionHidden('restore')
            ->callAction('delete')
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted($this->tenant);
        $this->get('http://localhost/m/konak-evi/moda/menu')->assertNotFound();
        $this->get('http://localhost/m/konak/moda/menu')->assertNotFound();
        $this->actingAs($member)->get('/app/konak-evi')->assertForbidden(); // no active membership left

        // Still listed (trashed filter) and viewable, and the slug stays taken.
        $this->asSuperAdmin();
        $this->get("/admin/tenants/{$this->tenant->id}")->assertOk();
        $this->expectsValidation(fn () => $this->service()->changeSlug($this->superAdmin, Tenant::factory()->create(), 'konak-evi'));

        $this->assertFalse(User::factory()->platformStaff()->create()->can('restore', $this->tenant->fresh()));
        $this->assertFalse($this->superAdmin->can('forceDelete', $this->tenant->fresh()));

        Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
            ->assertActionHidden('delete')
            ->callAction('restore')
            ->assertHasNoActionErrors();

        $this->assertNotSoftDeleted($this->tenant);
        $this->get('http://localhost/m/konak-evi/moda/menu')->assertOk();
        $this->assertSame(
            [AdminAuditAction::TenantSlugChanged, AdminAuditAction::TenantDeleted, AdminAuditAction::TenantRestored],
            AdminAuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_the_audit_log_is_append_only_and_readable_by_every_platform_role(): void
    {
        $this->service()->suspend($this->superAdmin, $this->tenant);
        $event = AdminAuditEvent::query()->sole();

        $this->actingAs(User::factory()->platformStaff()->create())->get('/admin/audit-log')->assertOk()->assertSee(__('admin.audit.actions.tenant_suspended'));
        $this->actingAs(User::factory()->create())->get('/admin/audit-log')->assertForbidden();

        try {
            $event->forceFill(['action' => AdminAuditAction::TenantRestored])->save();
            $this->fail('Audit events must not be updated.');
        } catch (LogicException) {
        }

        try {
            $event->delete();
            $this->fail('Audit events must not be deleted.');
        } catch (LogicException) {
        }

        $this->expectException(QueryException::class);
        DB::table('admin_audit_events')->where('id', $event->id)->update(['ip' => '10.0.0.1']);
    }

    private function expectsValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException) {
        }
    }
}
