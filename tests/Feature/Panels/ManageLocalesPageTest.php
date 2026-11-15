<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\PlatformRole;
use App\Exceptions\PlanLimitExceededException;
use App\Filament\App\Pages\ManageLocales;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantLocale;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantLocaleService;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ManageLocalesPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['locales.supported' => ['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch']]);

        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $plan = Plan::factory()->withLimits(['max_languages' => 2])->create();
        app(SubscriptionService::class)->startTrial($this->tenant, $plan);
    }

    private function member(string $role = 'owner', ?Tenant $tenant = null): User
    {
        $factory = TenantMembership::factory()->for($tenant ?? $this->tenant);
        $membership = ($role === 'owner' ? $factory : $factory->{$role}())->create();

        if ($role !== 'owner') {
            $membership->locations()->attach(Location::factory()->for($tenant ?? $this->tenant)->create());
        }

        return $membership->user;
    }

    /**
     * Same state the panel middleware builds for a Livewire request.
     */
    private function page(User $user): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        $this->app->make(TenantContext::class)->set($this->tenant);

        return Livewire::test(ManageLocales::class);
    }

    public function test_owner_sees_the_page_and_its_navigation_entry(): void
    {
        $this->actingAs($this->member())
            ->get('/app/yaman-kahvesi/languages')
            ->assertOk()
            ->assertSee(__('panels.app.locales.title'))
            ->assertSee('Türkçe');

        $this->get('/app/yaman-kahvesi')
            ->assertOk()
            ->assertSee(__('panels.app.navigation.business'))
            ->assertSee(__('panels.app.locales.navigation'));
    }

    public function test_managers_and_branch_staff_are_forbidden_and_see_no_entry(): void
    {
        foreach (['manager', 'branchStaff'] as $role) {
            $user = $this->member($role);

            $this->actingAs($user)->get('/app/yaman-kahvesi/languages')->assertForbidden();
            $this->actingAs($user)->get('/app/yaman-kahvesi')
                ->assertOk()
                ->assertDontSee(__('panels.app.locales.navigation'));
        }
    }

    public function test_non_members_get_404_and_platform_roles_grant_nothing(): void
    {
        $outsider = $this->member('owner', Tenant::factory()->create());
        $this->actingAs($outsider)->get('/app/yaman-kahvesi/languages')->assertNotFound();

        $admin = User::factory()->create();
        $admin->forceFill(['platform_role' => PlatformRole::SuperAdmin])->save();
        $this->actingAs($admin)->get('/app/yaman-kahvesi/languages')->assertForbidden();
    }

    public function test_livewire_requests_are_reauthorized(): void
    {
        $this->page($this->member('manager'))->assertForbidden();
    }

    public function test_owner_adds_a_language(): void
    {
        $this->page($this->member())
            ->assertCanSeeTableRecords(TenantLocale::query()->get())
            ->callTableAction('addLocale', data: ['locale' => 'en'])
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('panels.app.locales.notifications.added'));

        $this->assertSame(['tr', 'en'], $this->tenant->enabledLocales());
    }

    public function test_the_plan_limit_blocks_adding_on_the_server(): void
    {
        $page = $this->page($this->member());
        $page->callTableAction('addLocale', data: ['locale' => 'en']);

        $page->assertTableActionDisabled('addLocale');

        // Even when the disabled button is bypassed, the service refuses.
        $this->assertThrows(
            fn () => app(TenantLocaleService::class)->enable('de'),
            PlanLimitExceededException::class,
        );
        $this->assertSame(['tr', 'en'], $this->tenant->enabledLocales());
    }

    public function test_an_unsupported_language_cannot_be_added_by_tampering(): void
    {
        $this->page($this->member())
            ->callTableAction('addLocale', data: ['locale' => 'xx'])
            ->assertHasTableActionErrors(['locale']);

        $this->assertSame(['tr'], $this->tenant->enabledLocales());
    }

    public function test_owner_changes_the_default_and_removes_a_language(): void
    {
        $page = $this->page($this->member());
        $page->callTableAction('addLocale', data: ['locale' => 'en']);

        $tr = TenantLocale::query()->where('locale', 'tr')->sole();
        $en = TenantLocale::query()->where('locale', 'en')->sole();

        $page->assertTableActionHidden('makeDefault', $tr)
            ->assertTableActionHidden('disable', $tr)
            ->callTableAction('makeDefault', $en)
            ->assertNotified(__('panels.app.locales.notifications.default_changed'));

        $this->assertSame('en', $this->tenant->fresh()->default_locale);

        $page->callTableAction('disable', $tr)
            ->assertNotified(__('panels.app.locales.notifications.removed'));

        $this->assertSame(['en'], $this->tenant->fresh()->enabledLocales());
    }

    public function test_reordering_goes_through_the_service_and_ignores_foreign_keys(): void
    {
        $page = $this->page($this->member());
        $page->callTableAction('addLocale', data: ['locale' => 'en']);

        $tr = TenantLocale::query()->where('locale', 'tr')->sole();
        $en = TenantLocale::query()->where('locale', 'en')->sole();

        $page->call('reorderTable', [(string) $en->id, (string) $tr->id]);
        $this->assertSame(['en', 'tr'], TenantLocale::query()->orderBy('sort_order')->pluck('locale')->all());

        // A key of another tenant's row is not resolvable: the order is
        // incomplete and rejected, nothing changes on either side.
        $other = Tenant::factory()->create();
        $foreignId = DB::table('tenant_locales')->where('tenant_id', $other->id)->value('id');

        $page->call('reorderTable', [(string) $foreignId, (string) $tr->id])
            ->assertNotified(__('tenancy.locales.errors.invalid_order'));

        $this->assertSame(['en', 'tr'], TenantLocale::query()->orderBy('sort_order')->pluck('locale')->all());
        $this->assertDatabaseHas('tenant_locales', ['id' => $foreignId, 'tenant_id' => $other->id, 'sort_order' => 0]);
    }
}
