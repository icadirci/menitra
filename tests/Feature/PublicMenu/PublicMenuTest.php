<?php

declare(strict_types=1);

namespace Tests\Feature\PublicMenu;

use App\Events\QrCodeScanned;
use App\Models\Location;
use App\Models\LocationItemState;
use App\Models\LocationMenu;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Menu\MenuCacheKeys;
use App\Services\Menu\MenuUrlGenerator;
use App\Services\Menu\PublicMenuService;
use App\Services\Tenancy\TenantLocaleService;
use App\Tenancy\ResolvedTenantFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private const string PATH = 'http://localhost/m/konak';

    private const string SUB = 'http://konak.localhost';

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    private Menu $menu;

    private MenuCategory $coffee;

    private MenuItem $turkish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak');
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        $this->menu = $this->menu($this->tenant, 'menu', [$this->moda, $this->cihangir]);
        $this->coffee = $this->category($this->menu, 'Kahveler');
        $this->turkish = $this->item($this->coffee, 'Türk Kahvesi', 15000, english: 'Turkish Coffee');
    }

    public function test_subdomain_and_path_render_the_same_menu(): void
    {
        $path = $this->get(self::PATH.'/moda/menu')->assertOk()->assertSee('Türk Kahvesi')->assertSee('₺150');
        $sub = $this->get(self::SUB.'/moda/menu')->assertOk()->assertSee('Türk Kahvesi');

        $this->assertSame($path->headers->get('ETag'), $sub->headers->get('ETag'));
        $this->assertSame('http://konak.localhost/moda/menu', app(MenuUrlGenerator::class)->menu('konak', 'moda', 'menu'));
    }

    public function test_menu_pages_set_no_cookies_and_are_publicly_cacheable(): void
    {
        $response = $this->get(self::PATH.'/moda/menu')->assertOk();

        $this->assertSame([], $response->headers->getCookies());
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertNotNull($response->headers->get('ETag'));

        $this->get(self::PATH.'/moda/menu', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
    }

    public function test_the_tenant_home_lists_locations_and_a_location_with_one_menu_renders_it(): void
    {
        $this->get(self::SUB.'/')->assertOk()->assertSee('http://konak.localhost/moda', false)->assertSee(__('menu.choose_location'));
        $this->get(self::SUB.'/moda')->assertOk()->assertSee('Türk Kahvesi');
    }

    public function test_hidden_content_unpublished_menus_and_inactive_locations_are_not_rendered(): void
    {
        $this->item($this->coffee, 'Gizli Ürün', attributes: ['is_visible' => false]);
        $hidden = $this->category($this->menu, 'Gizli Kategori', visible: false);
        $this->item($hidden, 'Gizli Kategorideki Ürün');
        $draft = $this->menu($this->tenant, 'taslak', [$this->moda], published: false, name: 'Taslak Menü');

        $this->get(self::PATH.'/moda/menu')->assertOk()
            ->assertDontSee('Gizli Ürün')
            ->assertDontSee('Gizli Kategori');
        $this->get(self::PATH.'/moda/taslak')->assertNotFound();
        $this->assertNotNull($draft);

        $this->inTenant($this->tenant, fn () => $this->cihangir->update(['is_active' => false]));
        $this->get(self::PATH.'/cihangir/menu')->assertNotFound();
    }

    public function test_detached_menus_and_unknown_slugs_are_404(): void
    {
        $this->inTenant($this->tenant, fn () => LocationMenu::query()->where('location_id', $this->cihangir->getKey())->get()->each->delete());

        $this->get(self::PATH.'/cihangir/menu')->assertNotFound();
        $this->get('http://localhost/m/yok')->assertNotFound();
        $this->get('http://yok.localhost/')->assertNotFound();
        $this->get(self::PATH.'/yok')->assertNotFound();
    }

    public function test_another_tenants_location_is_not_reachable_through_this_tenant(): void
    {
        $other = $this->subscribedTenant('baska');
        $this->location($other, 'kadikoy', true);

        $this->get(self::PATH.'/kadikoy')->assertNotFound();
    }

    public function test_sold_out_is_per_location_and_timed_sold_out_ends_on_time(): void
    {
        $until = CarbonImmutable::now()->addHour();
        $this->inTenant($this->tenant, fn () => LocationItemState::query()->create([
            'location_id' => $this->moda->getKey(), 'menu_item_id' => $this->turkish->getKey(),
            'is_sold_out' => true, 'sold_out_until' => $until,
        ]));

        $this->get(self::PATH.'/moda/menu')->assertSee(__('menu.sold_out_until', ['time' => $until->setTimezone('Europe/Istanbul')->format('H:i')]));
        $this->get(self::PATH.'/cihangir/menu')->assertDontSee(__('menu.sold_out'));

        // Same cached snapshot, later clock: on sale again without a cache bump.
        $this->travel(2)->hours();
        $this->get(self::PATH.'/moda/menu')->assertDontSee(__('menu.sold_out'));
    }

    public function test_variants_show_a_from_price(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->turkish->update(['price' => null]);
            foreach ([['Tek', 15000], ['Duble', 21000]] as [$name, $price]) {
                $variant = $this->turkish->variants()->create(['price' => $price]);
                $variant->translations()->create(['locale' => 'tr', 'name' => $name]);
            }
        });

        $this->get(self::PATH.'/moda/menu')->assertOk()
            ->assertSee(__('menu.from_price', ['price' => '₺150']))
            ->assertSee('Duble')
            ->assertSee('₺210');
    }

    public function test_locale_comes_from_a_valid_lang_parameter_only(): void
    {
        $this->inTenant($this->tenant, fn () => $this->tenant->locales()->create(['locale' => 'en', 'sort_order' => 1]));

        $this->get(self::PATH.'/moda/menu?lang=en')->assertOk()->assertSee('Turkish Coffee')->assertSee('lang="en"', false);
        $this->get(self::PATH.'/moda/menu?lang=de')->assertOk()->assertSee('Türk Kahvesi')->assertSee('lang="tr"', false);
        // Missing translation falls back to the default locale.
        $this->item($this->coffee, 'Salep');
        $this->get(self::PATH.'/moda/menu?lang=en')->assertSee('Salep');
    }

    public function test_arabic_menus_render_right_to_left_with_western_digits(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->tenant->locales()->create(['locale' => 'ar', 'sort_order' => 1]);
            $this->turkish->translations()->create(['locale' => 'ar', 'name' => 'قهوة تركية']);
        });

        $this->get(self::PATH.'/moda/menu?lang=ar')->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('قهوة تركية')
            ->assertSee(__('menu.prices_note', [], 'ar'))
            ->assertSee('150')
            ->assertDontSee('١٥٠');

        $this->get(self::PATH.'/moda/menu')->assertOk()->assertSee('<html lang="tr" dir="ltr">', false);
    }

    public function test_enabling_disabling_and_reordering_locales_take_effect_while_the_resolution_cache_is_warm(): void
    {
        $this->inTenant($this->tenant, fn () => $this->tenant->locales()->create(['locale' => 'en', 'sort_order' => 1]));
        $this->get(self::PATH.'/moda/menu')->assertOk()->assertDontSee('?lang=ar', false);

        $this->inTenant($this->tenant, fn () => app(TenantLocaleService::class)->enable('ar'));
        $this->get(self::PATH.'/moda/menu?lang=ar')->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('?lang=ar', false);

        $this->inTenant($this->tenant, fn () => app(TenantLocaleService::class)->reorder(['tr', 'ar', 'en']));
        $this->assertSame(['tr', 'ar', 'en'], app(ResolvedTenantFactory::class)->byId($this->tenant->id)->locales);

        $this->inTenant($this->tenant, fn () => app(TenantLocaleService::class)->disable('ar'));
        $this->get(self::PATH.'/moda/menu?lang=ar')->assertOk()
            ->assertSee('<html lang="tr" dir="ltr">', false)
            ->assertDontSee('?lang=ar', false);
    }

    public function test_suspended_or_expired_tenants_get_the_unavailable_page(): void
    {
        $this->tenant->forceFill(['status' => 'suspended'])->save();

        $this->get(self::PATH.'/moda/menu')
            ->assertStatus(503)
            ->assertSee(__('menu.unavailable.title'))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('Türk Kahvesi');

        $this->tenant->forceFill(['status' => 'active'])->save();
        $this->inTenant($this->tenant, fn () => Subscription::query()->update(['trial_ends_at' => now()->subDays(30)]));
        Cache::flush();

        $this->get(self::PATH.'/moda/menu')->assertStatus(503);
    }

    public function test_expiry_takes_effect_while_the_resolution_cache_is_warm(): void
    {
        $this->get(self::PATH.'/moda/menu')->assertOk();

        $this->travel(config('subscriptions.grace_days') + 20)->days();

        $this->get(self::PATH.'/moda/menu')->assertStatus(503);
    }

    public function test_a_warm_menu_request_runs_no_queries(): void
    {
        $this->get(self::PATH.'/moda/menu')->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->get(self::PATH.'/moda/menu')->assertOk()->assertSee('Türk Kahvesi');

        $this->assertSame(0, $queries);
    }

    public function test_editing_an_item_invalidates_every_location_sharing_the_menu(): void
    {
        $this->get(self::PATH.'/moda/menu')->assertSee('Türk Kahvesi');
        $this->get(self::PATH.'/cihangir/menu')->assertSee('Türk Kahvesi');

        $this->inTenant($this->tenant, fn () => $this->turkish->translations()->where('locale', 'tr')->first()->update(['name' => 'Közde Kahve']));

        $this->get(self::PATH.'/moda/menu')->assertSee('Közde Kahve');
        $this->get(self::PATH.'/cihangir/menu')->assertSee('Közde Kahve');
    }

    public function test_sold_out_bumps_only_that_location(): void
    {
        $versionB = Cache::get(MenuCacheKeys::locationVersion($this->tenant->id, $this->cihangir->id));

        $this->inTenant($this->tenant, fn () => LocationItemState::query()->create([
            'location_id' => $this->moda->getKey(), 'menu_item_id' => $this->turkish->getKey(), 'is_sold_out' => true,
        ]));

        $this->assertSame($versionB, Cache::get(MenuCacheKeys::locationVersion($this->tenant->id, $this->cihangir->id)));
        $this->assertGreaterThan(1, (int) Cache::get(MenuCacheKeys::locationVersion($this->tenant->id, $this->moda->id)));
    }

    public function test_a_rolled_back_write_does_not_bump_versions(): void
    {
        $before = Cache::get(MenuCacheKeys::tenantVersion($this->tenant->id));

        try {
            DB::transaction(function (): void {
                $this->inTenant($this->tenant, fn () => $this->turkish->update(['price' => 1]));
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame($before, Cache::get(MenuCacheKeys::tenantVersion($this->tenant->id)));
    }

    public function test_the_snapshot_contains_only_allow_listed_data(): void
    {
        $resolved = app(ResolvedTenantFactory::class)->make($this->tenant);
        $snapshot = app(PublicMenuService::class)->menu($resolved, 'tr', 'moda', 'menu');

        $serialized = serialize($snapshot);

        foreach (['tenant_id', 'password', 'email_verified', 'status', 'subscription', 'Illuminate\\Database'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serialized, "Snapshot leaks [{$forbidden}].");
        }
    }

    public function test_qr_codes_redirect_to_the_current_url(): void
    {
        Event::fake([QrCodeScanned::class]);

        $qr = $this->inTenant($this->tenant, fn () => $this->moda->qrCodes()->create(['menu_id' => $this->menu->getKey()]));

        $this->get('http://localhost/q/'.$qr->code)->assertStatus(302)->assertRedirect('http://konak.localhost/moda/menu');
        Event::assertDispatched(QrCodeScanned::class);

        // Slug change: the printed code follows.
        $this->tenant->update(['slug' => 'konak-evi']);
        $this->get('http://localhost/q/'.$qr->code)->assertRedirect('http://konak-evi.localhost/moda/menu');

        // Detached menu: falls back to the location page.
        $this->inTenant($this->tenant, fn () => LocationMenu::query()->where('location_id', $this->moda->getKey())->get()->each->delete());
        $this->get('http://localhost/q/'.$qr->code)->assertRedirect('http://konak-evi.localhost/moda');
    }

    public function test_inactive_and_unknown_qr_codes(): void
    {
        $qr = $this->inTenant($this->tenant, fn () => $this->moda->qrCodes()->create(['is_active' => false]));

        $this->get('http://localhost/q/'.$qr->code)
            ->assertStatus(503)
            ->assertDontSee($this->tenant->translate('tr')->name);
        $this->get('http://localhost/q/ZZZZZZZZZZ')->assertNotFound();
        $this->get('http://konak.localhost/q/'.$qr->code)->assertNotFound();
    }

    public function test_qr_codes_are_random_and_non_sequential(): void
    {
        $codes = $this->inTenant($this->tenant, fn () => collect(range(1, 5))->map(fn () => $this->moda->qrCodes()->create()->code));

        $this->assertCount(5, $codes->unique());
        $codes->each(fn (string $code) => $this->assertMatchesRegularExpression('/^['.QrCode::ALPHABET.']{10}$/', $code));
    }
}
