<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\MenuFontPair;
use App\Enums\MenuTheme;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Filament\App\Pages\ManageTheme;
use App\Filament\App\Resources\Locations\Pages\EditLocation;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\Menu\MenuCacheKeys;
use App\Services\Subscription\FeatureGate;
use App\Services\Themes\ThemeService;
use App\Support\Menu\ThemeSettings;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * B9 — Theme & appearance: page authorization, settings validation (incl.
 * contrast), per-theme persistence, premium_themes gating, the branch
 * override and cache invalidation. `minimal` is made "built" with test
 * fixture views (tests/Fixtures/views) to exercise premium flows.
 */
class ThemeManagementTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private const string PATH = 'http://localhost/m/konak';

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak', ['premium_themes' => 1]);
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        $menu = $this->menu($this->tenant, 'menu', [$this->moda, $this->cihangir]);
        $this->item($this->category($menu, 'Kahveler'), 'Türk Kahvesi', 15000);
    }

    private function buildMinimal(): void
    {
        View::addLocation(base_path('tests/Fixtures/views'));
    }

    private function member(string $role = 'owner'): User
    {
        $factory = TenantMembership::factory()->for($this->tenant);
        $membership = ($role === 'owner' ? $factory : $factory->{$role}())->create();

        if ($role !== 'owner') {
            $membership->locations()->attach($this->moda);
        }

        return $membership->user;
    }

    private function enter(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        $this->app->make(TenantContext::class)->set($this->tenant);
    }

    private function page(User $user): Testable
    {
        $this->enter($user);

        return Livewire::test(ManageTheme::class);
    }

    /**
     * @param  array<string, mixed>  $colors
     * @return array<string, mixed>
     */
    private function settings(MenuTheme $theme, array $colors = [], array $extra = []): array
    {
        $settings = $theme->defaults();
        $settings['colors'] = array_merge($settings['colors'], $colors);

        return array_replace_recursive($settings, $extra);
    }

    private function storedSettings(MenuTheme $theme): ?array
    {
        return $this->inTenant($this->tenant, fn () => ThemeSetting::query()->where('theme', $theme)->value('settings'));
    }

    private function dropPremium(): void
    {
        // A subscription change clears the resolution cache (MenuCacheObserver).
        $plan = Plan::factory()->withLimits(['max_locations' => 10, 'max_menus' => 10, 'max_items' => 100])->create();
        Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)->sole()->forceFill(['plan_id' => $plan->id])->save();
        // Limits are memoised per request; the test continues in the same one.
        app(FeatureGate::class)->flush();
    }

    // Authorization ---------------------------------------------------------

    public function test_owner_sees_the_page_with_coming_soon_themes(): void
    {
        $this->actingAs($this->member())
            ->get('/app/konak/theme')
            ->assertOk()
            ->assertSee(__('panels.app.theme.title'))
            ->assertSee(__('panels.app.theme.coming_soon'));
    }

    public function test_other_roles_are_forbidden_and_outsiders_get_404(): void
    {
        foreach (['manager', 'branchStaff'] as $role) {
            $this->actingAs($this->member($role))->get('/app/konak/theme')->assertForbidden();
        }

        $outsider = TenantMembership::factory()->for(Tenant::factory()->create())->create()->user;
        $this->actingAs($outsider)->get('/app/konak/theme')->assertNotFound();
    }

    public function test_livewire_requests_are_reauthorized(): void
    {
        $this->page($this->member('manager'))->assertForbidden();
    }

    // Settings --------------------------------------------------------------

    public function test_owner_saves_colours_font_and_layout(): void
    {
        $this->page($this->member())
            ->assertSet('data.theme', 'classic')
            ->assertSet('data.settings.colors.primary', '#23443A')
            ->set('data.settings.colors.primary', '#1a3a30')
            ->set('data.settings.font_pair', MenuFontPair::BricolageInter->value)
            ->set('data.settings.layout.show_images', false)
            ->set('data.settings.layout.image_shape', 'rounded')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified(__('panels.app.theme.saved'));

        $stored = $this->storedSettings(MenuTheme::Classic);
        $this->assertSame('#1A3A30', $stored['colors']['primary']);
        $this->assertSame('bricolage_inter', $stored['font_pair']);
        $this->assertEquals(['show_images' => false, 'show_featured' => true, 'image_shape' => 'rounded'], $stored['layout']);
    }

    public function test_invalid_values_and_low_contrast_are_rejected_on_the_server(): void
    {
        $this->enter($this->member());
        $service = app(ThemeService::class);

        $cases = [
            'settings.colors.primary' => $this->settings(MenuTheme::Classic, ['primary' => 'red; background:url(x)']),
            // Light grey text on the cream background: ~1.3:1.
            'settings.colors.text' => $this->settings(MenuTheme::Classic, ['text' => '#DDDDDD']),
            'settings.font_pair' => $this->settings(MenuTheme::Classic, extra: ['font_pair' => 'comic_sans']),
            'settings.layout.image_shape' => $this->settings(MenuTheme::Classic, extra: ['layout' => ['image_shape' => 'circle']]),
        ];

        foreach ($cases as $key => $settings) {
            try {
                $service->save($this->tenant, MenuTheme::Classic, $settings);
                $this->fail("Expected a validation error on {$key}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($key, $exception->errors());
            }
        }

        $this->assertNull($this->storedSettings(MenuTheme::Classic));
    }

    public function test_the_page_shows_contrast_errors_on_the_field(): void
    {
        $this->page($this->member())
            ->set('data.settings.colors.muted', '#E8DCCB')
            ->call('save')
            ->assertHasErrors(['data.settings.colors.muted']);

        $this->assertNull($this->storedSettings(MenuTheme::Classic));
    }

    public function test_unknown_keys_are_not_stored(): void
    {
        $this->enter($this->member());

        app(ThemeService::class)->save($this->tenant, MenuTheme::Classic, $this->settings(MenuTheme::Classic, ['evil' => '#000000'], ['css' => 'body{}']));

        $stored = $this->storedSettings(MenuTheme::Classic);
        $this->assertEqualsCanonicalizing(['colors', 'font_pair', 'layout'], array_keys($stored));
        $this->assertArrayNotHasKey('evil', $stored['colors']);
    }

    public function test_every_theme_has_valid_defaults(): void
    {
        foreach (MenuTheme::cases() as $theme) {
            $this->assertFalse($theme->validator($theme->defaults())->fails(), "Defaults of [{$theme->value}] fail validation or contrast.");
        }
    }

    public function test_stale_stored_settings_fall_back_to_defaults_per_section(): void
    {
        $settings = ThemeSettings::fromStored(MenuTheme::Classic, [
            'colors' => ['primary' => 'not-a-colour'],
            'font_pair' => MenuFontPair::System->value,
            'layout' => ['show_images' => false, 'image_shape' => 'hexagon'],
        ]);

        $this->assertSame(MenuTheme::Classic->defaults()['colors'], $settings->colors);
        $this->assertSame(MenuFontPair::System, $settings->fontPair);
        $this->assertFalse($settings->option('show_images'));
        $this->assertSame('arch', $settings->option('image_shape'));
        $this->assertStringContainsString('--menu-font-body:'.MenuFontPair::System->body().';', $settings->cssVariables());
    }

    // Theme selection and plan gating ---------------------------------------

    public function test_themes_without_views_cannot_be_selected(): void
    {
        $this->enter($this->member());

        $this->expectException(ValidationException::class);

        app(ThemeService::class)->save($this->tenant, MenuTheme::Bistro, MenuTheme::Bistro->defaults());
    }

    public function test_switching_back_to_a_theme_restores_its_settings(): void
    {
        $this->buildMinimal();

        $this->page($this->member())
            ->set('data.settings.colors.primary', '#1A3A30')
            ->call('save')
            ->assertHasNoErrors()
            ->set('data.theme', 'minimal')
            ->assertSet('data.settings.colors.primary', MenuTheme::Minimal->defaults()['colors']['primary'])
            ->set('data.settings.colors.primary', '#123456')
            ->call('save')
            ->assertHasNoErrors()
            ->set('data.theme', 'classic')
            ->assertSet('data.settings.colors.primary', '#1A3A30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(MenuTheme::Classic, $this->tenant->fresh()->theme);
        $this->assertSame('#1A3A30', $this->storedSettings(MenuTheme::Classic)['colors']['primary']);
        $this->assertSame('#123456', $this->storedSettings(MenuTheme::Minimal)['colors']['primary']);
    }

    public function test_premium_themes_need_the_plan_feature(): void
    {
        $this->buildMinimal();
        $this->dropPremium();
        $this->enter($this->member());

        try {
            app(ThemeService::class)->save($this->tenant, MenuTheme::Minimal, MenuTheme::Minimal->defaults());
            $this->fail('Expected the plan to block a premium theme.');
        } catch (PlanLimitExceededException) {
        }

        $this->assertSame(MenuTheme::Classic, $this->tenant->fresh()->theme);
        $this->assertNull($this->storedSettings(MenuTheme::Minimal));

        // The page refuses it as well (the option is disabled).
        Livewire::test(ManageTheme::class)
            ->set('data.theme', 'minimal')
            ->call('save')
            ->assertHasErrors(['data.theme']);

        $this->assertSame(MenuTheme::Classic, $this->tenant->fresh()->theme);
    }

    public function test_losing_premium_falls_back_to_classic_and_keeps_settings(): void
    {
        $this->buildMinimal();
        $this->enter($this->member());
        app(ThemeService::class)->save($this->tenant, MenuTheme::Minimal, $this->settings(MenuTheme::Minimal, ['primary' => '#123456']));

        $this->get(self::PATH.'/moda/menu')->assertOk()->assertSee('data-theme="minimal"', false)->assertSee('--menu-primary:#123456', false);

        $this->dropPremium();

        $this->get(self::PATH.'/moda/menu')->assertOk()->assertSee('data-theme="classic"', false);
        $this->assertSame('#123456', $this->storedSettings(MenuTheme::Minimal)['colors']['primary']);
        $this->assertSame(MenuTheme::Minimal, $this->tenant->fresh()->theme);

        // Keeping (and editing) the theme already in use is not blocked by the plan.
        app(ThemeService::class)->save($this->tenant, MenuTheme::Minimal, $this->settings(MenuTheme::Minimal, ['primary' => '#223344']));
        $this->assertSame('#223344', $this->storedSettings(MenuTheme::Minimal)['colors']['primary']);

        Livewire::test(ManageTheme::class)->assertSee(__('panels.app.theme.premium_lost.title'));
    }

    // Branch override ---------------------------------------------------------

    public function test_owner_sets_a_branch_theme_override(): void
    {
        $this->buildMinimal();
        $this->enter($this->member());

        Livewire::test(EditLocation::class, ['record' => $this->cihangir->getRouteKey()])
            ->set('data.theme', 'minimal')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(MenuTheme::Minimal, $this->cihangir->fresh()->theme);

        $this->get(self::PATH.'/cihangir/menu')->assertSee('data-theme="minimal"', false);
        $this->get(self::PATH.'/moda/menu')->assertSee('data-theme="classic"', false);

        Livewire::test(EditLocation::class, ['record' => $this->cihangir->getRouteKey()])
            ->set('data.theme', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->cihangir->fresh()->theme);
    }

    public function test_managers_cannot_change_the_branch_theme(): void
    {
        $this->buildMinimal();
        $this->enter($this->member('manager'));

        Livewire::test(EditLocation::class, ['record' => $this->moda->getRouteKey()])
            ->assertFormFieldIsDisabled('theme')
            ->set('data.theme', 'minimal')
            ->set('data.translations.tr.name', 'Moda Şube')
            ->call('save');

        $this->assertNull($this->moda->fresh()->theme);
    }

    public function test_the_branch_override_respects_built_themes_and_the_plan(): void
    {
        $this->enter($this->member());

        try {
            app(ThemeService::class)->setLocationTheme($this->cihangir, MenuTheme::Grid);
            $this->fail('Expected an unbuilt theme to be refused.');
        } catch (ValidationException) {
        }

        $this->buildMinimal();
        $this->dropPremium();

        $this->expectException(PlanLimitExceededException::class);
        app(ThemeService::class)->setLocationTheme($this->cihangir, MenuTheme::Minimal);
    }

    // Cache -------------------------------------------------------------------

    public function test_saving_settings_bumps_the_tenant_version_and_clears_resolution(): void
    {
        $this->get(self::PATH.'/moda/menu')->assertOk()->assertSee('--menu-primary:#23443A', false);
        $this->assertTrue(Cache::has(MenuCacheKeys::slug('konak')));
        $before = (int) (Cache::get(MenuCacheKeys::tenantVersion($this->tenant->id)) ?? 1);

        $this->enter($this->member());
        app(ThemeService::class)->save($this->tenant, MenuTheme::Classic, $this->settings(MenuTheme::Classic, ['primary' => '#1A3A30']));

        $this->assertGreaterThan($before, (int) Cache::get(MenuCacheKeys::tenantVersion($this->tenant->id)));
        $this->assertFalse(Cache::has(MenuCacheKeys::slug('konak')));
        $this->get(self::PATH.'/moda/menu')->assertSee('--menu-primary:#1A3A30', false);
        $this->get(self::PATH.'/cihangir/menu')->assertSee('--menu-primary:#1A3A30', false);
    }

    public function test_a_warm_request_with_saved_settings_runs_no_queries(): void
    {
        $this->enter($this->member());
        app(ThemeService::class)->save($this->tenant, MenuTheme::Classic, $this->settings(MenuTheme::Classic, extra: ['layout' => ['show_images' => false]]));
        app(TenantContext::class)->clear();

        $this->get(self::PATH.'/moda/menu')->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->get(self::PATH.'/moda/menu')->assertOk()->assertSee('Türk Kahvesi')->assertDontSee('k-pic--row', false);
        $this->assertSame(0, $queries);
    }

    public function test_a_rolled_back_theme_save_does_not_bump(): void
    {
        $this->enter($this->member());
        $before = Cache::get(MenuCacheKeys::tenantVersion($this->tenant->id));

        try {
            DB::transaction(function (): void {
                app(ThemeService::class)->save($this->tenant, MenuTheme::Classic, MenuTheme::Classic->defaults());
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame($before, Cache::get(MenuCacheKeys::tenantVersion($this->tenant->id)));
        $this->assertNull($this->storedSettings(MenuTheme::Classic));
    }

    public function test_settings_are_isolated_between_tenants(): void
    {
        $other = $this->subscribedTenant('baska');
        $this->inTenant($other, fn () => app(ThemeService::class)->createDefaults(MenuTheme::Classic));

        $this->enter($this->member());
        app(ThemeService::class)->save($this->tenant, MenuTheme::Classic, $this->settings(MenuTheme::Classic, ['primary' => '#1A3A30']));

        $this->assertSame('#23443A', $this->inTenant($other, fn () => ThemeSetting::query()->sole()->settings['colors']['primary']));
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => ThemeSetting::query()->count()));

        $this->expectException(CrossTenantWriteException::class);
        app(ThemeService::class)->save($other, MenuTheme::Classic, MenuTheme::Classic->defaults());
    }
}
