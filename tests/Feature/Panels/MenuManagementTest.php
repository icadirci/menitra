<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Exceptions\PlanLimitExceededException;
use App\Filament\App\Pages\ManageStock;
use App\Filament\App\Resources\ExtraGroups\Pages\CreateExtraGroup;
use App\Filament\App\Resources\MenuItems\MenuItemResource;
use App\Filament\App\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\App\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\App\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\App\Resources\Menus\Pages\CreateMenu;
use App\Filament\App\Resources\Menus\Pages\EditMenu;
use App\Filament\App\Resources\Menus\Pages\ListMenus;
use App\Filament\App\Resources\Menus\RelationManagers\LocationsRelationManager;
use App\Models\ExtraGroup;
use App\Models\Location;
use App\Models\LocationItemState;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Menus\MenuContentService;
use App\Services\Menus\MenuService;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    /** Attached to both branches. */
    private Menu $shared;

    /** Attached to Moda only. */
    private Menu $breakfast;

    private MenuCategory $sharedCategory;

    private MenuCategory $breakfastCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak', ['max_menus' => 3, 'max_items' => 5]);
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        $this->shared = $this->menu($this->tenant, 'menu', [$this->moda, $this->cihangir]);
        $this->breakfast = $this->menu($this->tenant, 'kahvalti', [$this->moda], name: 'Kahvaltı');
        $this->sharedCategory = $this->category($this->shared, 'Kahveler');
        $this->breakfastCategory = $this->category($this->breakfast, 'Tabaklar');
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

    /**
     * @param  array<string, mixed>  $params
     */
    private function livewire(User $user, string $component, array $params = []): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        app(TenantContext::class)->set($this->tenant);

        return Livewire::test($component, $params);
    }

    public function test_owner_reaches_every_menu_module(): void
    {
        $owner = $this->owner();

        foreach (['menus', 'items', 'extra-groups', 'stock', "menus/{$this->shared->id}/edit"] as $path) {
            $this->actingAs($owner)->get("/app/konak/{$path}")->assertOk();
        }
    }

    public function test_manager_edits_only_menus_fully_inside_their_branches(): void
    {
        $manager = $this->member('manager', $this->moda);

        $this->livewire($manager, ListMenus::class)
            ->assertCanSeeTableRecords([$this->breakfast])
            ->assertCanNotSeeTableRecords([$this->shared]);

        $this->actingAs($manager)->get("/app/konak/menus/{$this->breakfast->id}/edit")->assertOk();
        $this->actingAs($manager)->get("/app/konak/menus/{$this->shared->id}/edit")->assertNotFound();
        $this->actingAs($manager)->get('/app/konak/menus/create')->assertForbidden();
        $this->actingAs($manager)->get('/app/konak/extra-groups')->assertForbidden();

        // Assigned to both branches → the shared menu becomes editable.
        $both = $this->member('manager', $this->moda, $this->cihangir);
        $this->actingAs($both)->get("/app/konak/menus/{$this->shared->id}/edit")->assertOk();
    }

    public function test_unattached_menus_are_owner_only(): void
    {
        $loose = $this->menu($this->tenant, 'taslak', []);
        $manager = $this->member('manager', $this->moda, $this->cihangir);

        $this->actingAs($manager)->get("/app/konak/menus/{$loose->id}/edit")->assertNotFound();
        $this->actingAs($this->owner())->get("/app/konak/menus/{$loose->id}/edit")->assertOk();
    }

    public function test_only_owners_attach_menus_to_locations(): void
    {
        $manager = $this->member('manager', $this->moda);

        $this->livewire($manager, EditMenu::class, ['record' => $this->breakfast->getKey()])->assertOk();
        $this->assertFalse(LocationsRelationManager::canViewForRecord($this->breakfast, EditMenu::class));
        $this->assertFalse($manager->can('manageLocations', $this->breakfast));
        $this->assertTrue($this->owner()->can('manageLocations', $this->breakfast));
    }

    public function test_branch_staff_has_no_menu_access_but_manages_stock(): void
    {
        $staff = $this->member('branchStaff', $this->moda);

        $this->actingAs($staff)->get('/app/konak/menus')->assertForbidden();
        $this->actingAs($staff)->get('/app/konak/items')->assertForbidden();
        $this->actingAs($staff)->get('/app/konak/stock')->assertOk();
    }

    public function test_owner_creates_a_menu_within_the_plan_limit(): void
    {
        $data = ['translations' => ['tr' => ['name' => 'Akşam', 'description' => null]], 'slug' => 'aksam', 'type' => 'dinner', 'is_published' => true];

        $this->livewire($this->owner(), CreateMenu::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $this->assertTrue(Menu::query()->where('slug', 'aksam')->exists());

        $this->livewire($this->owner(), ListMenus::class)->assertActionDisabled('create');
        $this->assertThrows(
            fn () => app(MenuService::class)->create(['slug' => 'gece', 'type' => 'other', 'translations' => ['tr' => ['name' => 'Gece']]]),
            PlanLimitExceededException::class,
        );
    }

    public function test_manager_creates_items_only_in_editable_menus(): void
    {
        $manager = $this->member('manager', $this->moda);
        $data = fn (MenuCategory $category): array => [
            'menu_category_id' => $category->getKey(),
            'price' => '85,50',
            'translations' => ['tr' => ['name' => 'Menemen', 'description' => null]],
            'is_visible' => true, 'is_available' => true,
            'allergens' => ['eggs'], 'dietary_labels' => ['vegetarian'],
        ];

        $this->livewire($manager, CreateMenuItem::class)->fillForm($data($this->breakfastCategory))->call('create')->assertHasNoFormErrors();

        $item = MenuItem::query()->whereHas('translations', fn ($q) => $q->where('name', 'Menemen'))->sole();
        $this->assertSame(8550, $item->price);
        $this->assertSame(['eggs'], $item->allergens()->get()->map(fn ($row) => $row->allergen->value)->all());

        // A tampered category of a non-editable menu is refused on the server.
        $this->livewire($manager, CreateMenuItem::class)->fillForm($data($this->sharedCategory))->call('create');
        $this->assertSame(0, MenuItem::query()->where('menu_category_id', $this->sharedCategory->getKey())->count());
        $this->assertFalse(MenuItemResource::canUseCategory($this->sharedCategory->getKey()));
        $this->assertTrue(MenuItemResource::canUseCategory($this->breakfastCategory->getKey()));

        $this->livewire($manager, ListMenuItems::class)->assertCanSeeTableRecords([$item]);
    }

    public function test_items_of_other_menus_are_not_reachable_for_managers(): void
    {
        $item = $this->item($this->sharedCategory, 'Türk Kahvesi');
        $manager = $this->member('manager', $this->moda);

        $this->actingAs($manager)->get("/app/konak/items/{$item->id}/edit")->assertNotFound();
        $this->livewire($this->owner(), EditMenuItem::class, ['record' => $item->getKey()])->assertOk();
    }

    public function test_the_item_limit_is_enforced_by_the_service(): void
    {
        foreach (range(1, 5) as $i) {
            $this->item($this->sharedCategory, "Ürün {$i}");
        }

        app(TenantContext::class)->set($this->tenant);
        $this->assertThrows(fn () => app(MenuContentService::class)->saveItem([
            'menu_category_id' => $this->sharedCategory->getKey(),
            'translations' => ['tr' => ['name' => 'Fazla']],
        ]), PlanLimitExceededException::class);
    }

    public function test_an_item_has_at_most_one_default_variant(): void
    {
        $item = $this->item($this->sharedCategory, 'Türk Kahvesi', null);
        app(TenantContext::class)->set($this->tenant);
        $service = app(MenuContentService::class);

        $single = $service->saveVariant($item, ['price' => 15000, 'is_default' => true, 'translations' => ['tr' => ['name' => 'Tek']]]);
        $double = $service->saveVariant($item, ['price' => 21000, 'is_default' => true, 'translations' => ['tr' => ['name' => 'Duble']]]);

        $this->assertFalse($single->refresh()->is_default);
        $this->assertTrue($double->refresh()->is_default);
    }

    public function test_extra_groups_are_owner_only_and_validated(): void
    {
        $this->livewire($this->owner(), CreateExtraGroup::class)
            ->fillForm(['translations' => ['tr' => ['name' => 'Şeker']], 'selection_type' => 'single', 'min_select' => 0])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = ExtraGroup::query()->sole();
        $this->assertSame(1, $group->max_select);
    }

    public function test_stock_status_is_per_branch_and_limited_to_assigned_branches(): void
    {
        $item = $this->item($this->sharedCategory, 'Türk Kahvesi');
        $staff = $this->member('branchStaff', $this->moda);

        $this->livewire($staff, ManageStock::class)
            ->assertCanSeeTableRecords([$item])
            ->callTableAction('soldOutIndefinitely', $item)
            ->assertNotified(__('panels.app.stock.saved'));

        $state = LocationItemState::query()->sole();
        $this->assertSame($this->moda->getKey(), $state->location_id);
        $this->assertNull($state->sold_out_until);

        // A tampered filter value for another branch falls back to an assigned one.
        $this->livewire($staff, ManageStock::class)
            ->set('tableFilters.location.value', (string) $this->cihangir->getKey())
            ->callTableAction('available', $item);
        $this->assertSame(0, LocationItemState::query()->count());
    }

    public function test_the_database_rejects_cross_tenant_content_links(): void
    {
        $other = $this->subscribedTenant('baska');
        $foreignMenu = $this->menu($other, 'menu', [$this->location($other, 'x', true)]);

        $this->expectException(QueryException::class);

        DB::table('menu_categories')->insert([
            'tenant_id' => $this->tenant->getKey(),
            'menu_id' => $foreignMenu->getKey(),
            'is_visible' => true,
            'sort_order' => 0,
        ]);
    }
}
