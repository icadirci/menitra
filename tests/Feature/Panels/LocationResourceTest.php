<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Exceptions\PlanLimitExceededException;
use App\Filament\App\Resources\Locations\Pages\CreateLocation;
use App\Filament\App\Resources\Locations\Pages\EditLocation;
use App\Filament\App\Resources\Locations\Pages\ListLocations;
use App\Filament\App\Resources\Locations\RelationManagers\SpecialHoursRelationManager;
use App\Models\Location;
use App\Models\LocationOpeningHour;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Locations\LocationService;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class LocationResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $main;

    private Location $kadikoy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        app(SubscriptionService::class)->startTrial($this->tenant, Plan::factory()->withLimits(['max_locations' => 3])->create());

        $this->main = Location::factory()->for($this->tenant)->create(['slug' => 'main', 'is_default' => true]);
        $this->kadikoy = Location::factory()->for($this->tenant)->create(['slug' => 'kadikoy']);
    }

    private function owner(): User
    {
        return TenantMembership::factory()->for($this->tenant)->create()->user;
    }

    private function member(string $role, Location ...$locations): User
    {
        $membership = TenantMembership::factory()->for($this->tenant)->{$role}()->create();
        $membership->locations()->attach(array_map(fn (Location $location) => $location->getKey(), $locations));

        return $membership->user;
    }

    private function enter(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        $this->app->make(TenantContext::class)->set($this->tenant);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function livewire(User $user, string $component, array $params = []): Testable
    {
        $this->enter($user);

        return Livewire::test($component, $params);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(string $slug, string $name): array
    {
        return [
            'translations' => ['tr' => ['name' => $name, 'description' => null]],
            'slug' => $slug,
            'is_active' => true,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'opening_hours' => [],
        ];
    }

    public function test_owner_sees_every_branch_and_the_navigation_entry(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get('/app/yaman-kahvesi/locations')->assertOk()->assertSee('kadikoy');
        $this->actingAs($owner)->get('/app/yaman-kahvesi')->assertSee(__('panels.app.locations.navigation'));

        $this->livewire($owner, ListLocations::class)->assertCanSeeTableRecords([$this->main, $this->kadikoy]);
    }

    public function test_manager_sees_and_reaches_only_assigned_branches(): void
    {
        $manager = $this->member('manager', $this->kadikoy);

        $this->livewire($manager, ListLocations::class)
            ->assertCanSeeTableRecords([$this->kadikoy])
            ->assertCanNotSeeTableRecords([$this->main]);

        $this->actingAs($manager)->get("/app/yaman-kahvesi/locations/{$this->kadikoy->getKey()}/edit")->assertOk();
        $this->actingAs($manager)->get("/app/yaman-kahvesi/locations/{$this->main->getKey()}/edit")->assertNotFound();
        $this->actingAs($manager)->get('/app/yaman-kahvesi/locations/create')->assertForbidden();
    }

    public function test_branch_staff_can_view_but_not_edit(): void
    {
        $staff = $this->member('branchStaff', $this->kadikoy);

        $this->actingAs($staff)->get("/app/yaman-kahvesi/locations/{$this->kadikoy->getKey()}")->assertOk();
        $this->actingAs($staff)->get("/app/yaman-kahvesi/locations/{$this->kadikoy->getKey()}/edit")->assertForbidden();
    }

    public function test_another_tenants_branch_is_not_reachable(): void
    {
        $other = Tenant::factory()->create();
        $foreign = Location::factory()->for($other)->create();

        $this->actingAs($this->owner())
            ->get("/app/yaman-kahvesi/locations/{$foreign->getKey()}/edit")
            ->assertNotFound();
    }

    public function test_owner_creates_a_branch_with_hours(): void
    {
        $data = $this->formData('besiktas', 'Beşiktaş');
        $data['opening_hours'] = [
            ['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '14:00'],
            ['day_of_week' => 1, 'opens_at' => '17:00', 'closes_at' => '02:00'],
        ];

        $this->livewire($this->owner(), CreateLocation::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        $location = Location::query()->where('slug', 'besiktas')->sole();
        $this->assertSame((int) $this->tenant->getKey(), (int) $location->tenant_id);
        $this->assertFalse($location->is_default);
        $this->assertSame('Beşiktaş', $location->translate('tr')->name);
        $this->assertSame(2, $location->openingHours()->count());
    }

    public function test_slugs_are_unique_per_tenant_only(): void
    {
        $this->livewire($this->owner(), CreateLocation::class)
            ->fillForm($this->formData('kadikoy', 'Kadıköy 2'))
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        // Another tenant may use the same slug.
        $this->app->make(TenantContext::class)->clear();
        $other = Tenant::factory()->create();
        $this->assertNotNull(Location::factory()->for($other)->create(['slug' => 'kadikoy'])->getKey());
    }

    public function test_the_plan_limit_is_enforced_by_the_service(): void
    {
        Location::factory()->for($this->tenant)->create();

        $this->livewire($this->owner(), ListLocations::class)->assertActionDisabled('create');

        $this->livewire($this->owner(), CreateLocation::class)
            ->fillForm($this->formData('besiktas', 'Beşiktaş'))
            ->call('create')
            ->assertNotified();

        $this->assertFalse(Location::query()->where('slug', 'besiktas')->exists());
        $this->assertThrows(
            fn () => app(LocationService::class)->create($this->formData('moda', 'Moda')),
            PlanLimitExceededException::class,
        );
    }

    public function test_overlapping_weekly_hours_are_rejected(): void
    {
        $this->livewire($this->owner(), EditLocation::class, ['record' => $this->kadikoy->getKey()])
            ->fillForm(['opening_hours' => [
                ['day_of_week' => 2, 'opens_at' => '08:00', 'closes_at' => '15:00'],
                ['day_of_week' => 2, 'opens_at' => '14:00', 'closes_at' => '20:00'],
            ]])
            ->call('save')
            ->assertHasErrors(['data.opening_hours.1.opens_at']);

        $this->assertSame(0, $this->kadikoy->openingHours()->count());
    }

    public function test_manager_updates_hours_of_an_assigned_branch(): void
    {
        $manager = $this->member('manager', $this->kadikoy);

        $this->livewire($manager, EditLocation::class, ['record' => $this->kadikoy->getKey()])
            ->fillForm(['opening_hours' => [['day_of_week' => 6, 'opens_at' => '09:00', 'closes_at' => '23:30']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $row = $this->kadikoy->openingHours()->sole();
        $this->assertSame(6, $row->day_of_week);
        $this->assertSame('09:00:00', $row->opens_at);
    }

    public function test_the_default_branch_cannot_be_deactivated_or_deleted(): void
    {
        $owner = $this->owner();

        $this->livewire($owner, EditLocation::class, ['record' => $this->main->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasErrors(['data.is_active']);
        $this->assertTrue($this->main->refresh()->is_active);

        $this->livewire($owner, ListLocations::class)->assertTableActionHidden('delete', $this->main);
        $this->assertThrows(fn () => app(LocationService::class)->delete($this->main));
        $this->assertFalse($this->main->refresh()->trashed());
    }

    public function test_owner_switches_the_default_branch(): void
    {
        $this->livewire($this->owner(), ListLocations::class)
            ->callTableAction('makeDefault', $this->kadikoy)
            ->assertNotified(__('panels.app.locations.notifications.default_changed'));

        $this->assertTrue($this->kadikoy->refresh()->is_default);
        $this->assertFalse($this->main->refresh()->is_default);
    }

    public function test_manager_cannot_switch_the_default_or_delete(): void
    {
        $manager = $this->member('manager', $this->kadikoy);

        $this->livewire($manager, ListLocations::class)
            ->assertTableActionHidden('makeDefault', $this->kadikoy)
            ->assertTableActionHidden('delete', $this->kadikoy);
    }

    public function test_deleting_keeps_data_and_restoring_respects_the_limit(): void
    {
        $owner = $this->owner();
        $this->livewire($owner, ListLocations::class)->callTableAction('delete', $this->kadikoy);

        $this->assertSoftDeleted($this->kadikoy);
        $this->assertSame(1, $this->kadikoy->translations()->count());

        Location::factory()->for($this->tenant)->count(2)->create();
        $this->assertThrows(fn () => app(LocationService::class)->restore($this->kadikoy), PlanLimitExceededException::class);
        $this->assertSoftDeleted($this->kadikoy);
    }

    public function test_special_days(): void
    {
        $owner = $this->owner();
        $page = fn () => $this->livewire($owner, SpecialHoursRelationManager::class, [
            'ownerRecord' => $this->kadikoy,
            'pageClass' => EditLocation::class,
        ]);

        $page()->callTableAction('create', data: ['date' => '2027-03-20', 'is_closed' => true])->assertHasNoTableActionErrors();
        $this->assertTrue($this->kadikoy->specialHours()->sole()->is_closed);

        // A closed date can't also get opening intervals.
        $page()->callTableAction('create', data: ['date' => '2027-03-20', 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '16:00'])
            ->assertNotified(__('panels.app.hours.errors.already_closed'));
        $this->assertSame(1, $this->kadikoy->specialHours()->count());

        $page()->callTableAction('create', data: ['date' => '2027-03-21', 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '16:00'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(2, $this->kadikoy->specialHours()->count());
    }

    public function test_branch_staff_cannot_change_special_days(): void
    {
        $staff = $this->member('branchStaff', $this->kadikoy);

        $this->livewire($staff, SpecialHoursRelationManager::class, [
            'ownerRecord' => $this->kadikoy,
            'pageClass' => EditLocation::class,
        ])->assertTableActionHidden('create');
    }

    public function test_the_database_rejects_hours_for_another_tenants_branch(): void
    {
        $other = Tenant::factory()->create();
        $foreign = Location::factory()->for($other)->create();

        $this->expectException(QueryException::class);

        DB::table('location_opening_hours')->insert([
            'tenant_id' => $this->tenant->getKey(),
            'location_id' => $foreign->getKey(),
            'day_of_week' => 1,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
        ]);
    }

    public function test_hours_are_tenant_scoped(): void
    {
        $other = Tenant::factory()->create();
        $foreign = Location::factory()->for($other)->create();
        $this->app->make(TenantContext::class)->set($other);
        $foreign->openingHours()->create(['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00']);

        $this->app->make(TenantContext::class)->set($this->tenant);
        $this->assertSame(0, LocationOpeningHour::query()->count());
    }
}
