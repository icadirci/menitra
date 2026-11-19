<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\PlanFeature;
use App\Exceptions\CrossTenantWriteException;
use App\Filament\App\Pages\ManageTheme;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Menu\MenuCacheKeys;
use App\Services\Subscription\FeatureGate;
use App\Services\WaiterCalls\WaiterCallService;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * Waiter calls, step 2: the waiter_call plan feature and the tenant-wide
 * switch on the theme page (written only by WaiterCallService).
 */
class WaiterCallSettingsTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak', ['waiter_call' => 1]);
        $this->location($this->tenant, 'moda', true);
    }

    private function owner(): User
    {
        return TenantMembership::factory()->for($this->tenant)->create()->user;
    }

    private function page(User $user): Testable
    {
        $this->enter($user);

        return Livewire::test(ManageTheme::class);
    }

    private function enter(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        app(TenantContext::class)->set($this->tenant);
    }

    private function service(): WaiterCallService
    {
        return app(WaiterCallService::class);
    }

    private function dropFeature(): void
    {
        $plan = Plan::factory()->withLimits(['max_locations' => 10, 'waiter_call' => 0])->create();
        Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)->sole()->forceFill(['plan_id' => $plan->id])->save();
        app(FeatureGate::class)->flush();
    }

    public function test_the_switch_is_off_for_new_tenants_and_never_mass_assignable(): void
    {
        $this->assertFalse($this->tenant->refresh()->waiter_calls_enabled);

        $this->tenant->fill(['waiter_calls_enabled' => true])->save();
        $this->assertFalse($this->tenant->refresh()->waiter_calls_enabled);
    }

    public function test_owner_turns_waiter_calls_on_and_off_from_the_theme_page(): void
    {
        $before = (int) (Cache::get(MenuCacheKeys::tenantVersion($this->tenant->id)) ?? 1);

        $this->page($this->owner())
            ->assertSet('data.waiter_calls_enabled', false)
            ->assertFormFieldIsEnabled('waiter_calls_enabled')
            ->set('data.waiter_calls_enabled', true)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('panels.app.theme.saved'));

        $this->assertTrue($this->tenant->refresh()->waiter_calls_enabled);
        $this->assertTrue($this->service()->isActive($this->tenant));
        // The menu shows the buttons from cached content: the version moves on.
        $this->assertGreaterThan($before, (int) Cache::get(MenuCacheKeys::tenantVersion($this->tenant->id)));

        $this->page($this->owner())
            ->assertSet('data.waiter_calls_enabled', true)
            ->set('data.waiter_calls_enabled', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->tenant->refresh()->waiter_calls_enabled);
    }

    public function test_without_the_plan_feature_it_cannot_be_turned_on(): void
    {
        $this->dropFeature();

        $this->page($this->owner())
            ->assertFormFieldIsDisabled('waiter_calls_enabled')
            ->assertSee(__('panels.app.theme.waiter_calls.not_in_plan'))
            // A tampered request: the disabled field isn't taken over.
            ->set('data.waiter_calls_enabled', true)
            ->call('save');

        $this->assertFalse($this->tenant->refresh()->waiter_calls_enabled);

        // The service refuses on its own.
        $this->assertThrows(fn () => $this->service()->setEnabled($this->tenant, true), ValidationException::class);
        $this->assertFalse($this->tenant->refresh()->waiter_calls_enabled);
    }

    public function test_without_a_live_subscription_it_cannot_be_turned_on(): void
    {
        $this->enter($this->owner());
        Subscription::query()->update(['ended_at' => now()]);
        app(FeatureGate::class)->flush();

        $this->assertThrows(fn () => $this->service()->setEnabled($this->tenant, true), ValidationException::class);
    }

    public function test_losing_the_feature_hides_calls_but_keeps_the_switch_and_allows_turning_it_off(): void
    {
        $this->enter($this->owner());
        $this->service()->setEnabled($this->tenant, true);
        $this->dropFeature();

        $this->assertTrue($this->tenant->refresh()->waiter_calls_enabled);
        $this->assertFalse($this->service()->isActive($this->tenant));

        $this->page($this->owner())
            ->assertSee(__('panels.app.theme.waiter_calls.plan_lost'))
            ->assertFormFieldIsEnabled('waiter_calls_enabled')
            ->set('data.waiter_calls_enabled', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->tenant->refresh()->waiter_calls_enabled);
    }

    public function test_a_failed_theme_save_keeps_the_switch_unchanged(): void
    {
        $this->page($this->owner())
            ->set('data.waiter_calls_enabled', true)
            // Text on its own background: fails the contrast check.
            ->set('data.settings.colors.text', '#FFFFFF')
            ->set('data.settings.colors.bg', '#FFFFFF')
            ->call('save')
            ->assertHasFormErrors();

        $this->assertFalse($this->tenant->refresh()->waiter_calls_enabled);
    }

    public function test_other_roles_cannot_reach_the_switch(): void
    {
        foreach (['manager', 'branchStaff'] as $role) {
            $user = TenantMembership::factory()->for($this->tenant)->{$role}()->create()->user;
            $this->actingAs($user)->get('/app/konak/theme')->assertForbidden();
            $this->page($user)->assertForbidden();
        }

        $this->assertFalse($this->tenant->refresh()->waiter_calls_enabled);
    }

    public function test_the_service_only_acts_on_the_current_tenant(): void
    {
        $other = $this->subscribedTenant('baska', ['waiter_call' => 1]);
        $this->enter($this->owner());

        $this->assertThrows(fn () => $this->service()->setEnabled($other, true), CrossTenantWriteException::class);
        $this->assertFalse($other->refresh()->waiter_calls_enabled);
    }

    public function test_the_feature_is_not_advertised_or_editable_until_guests_can_call(): void
    {
        $this->assertTrue(PlanFeature::WaiterCall->isBoolean());
        $this->assertFalse(PlanFeature::WaiterCall->isAvailable());
    }
}
