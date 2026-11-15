<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\MenuTheme;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\Menu\MenuCacheKeys;
use App\Services\Menu\MenuUrlGenerator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Theme showcase (/temalar · /en/themes): live demo menus from DemoSeeder,
 * resolved through the public resolver; no hard-coded menu data.
 */
class ThemeShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_theme_links_to_its_live_demo_menu(): void
    {
        $this->seed(DemoSeeder::class);
        $urls = app(MenuUrlGenerator::class);

        $tr = $this->get('/temalar')->assertOk()->assertSee('<html lang="tr">', escape: false);
        $en = $this->get('/en/themes')->assertOk()->assertSee('<html lang="en">', escape: false);

        foreach (config('marketing.showcase') as $theme => $slug) {
            $tr->assertSee(__("marketing.showcase.themes.{$theme}.name", [], 'tr'))
                ->assertSee('href="'.$urls->tenant($slug).'?lang=tr"', escape: false);
            $en->assertSee(__("marketing.showcase.themes.{$theme}.name", [], 'en'))
                ->assertSee('href="'.$urls->tenant($slug).'?lang=en"', escape: false);
        }

        $this->assertEqualsCanonicalizing(array_column(MenuTheme::cases(), 'value'), array_keys(config('marketing.showcase')));
        $tr->assertDontSee(__('marketing.showcase.no_demo', [], 'tr'));
    }

    public function test_each_demo_business_renders_its_theme(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (config('marketing.showcase') as $theme => $slug) {
            if ($slug === 'kahve-konagi') {
                continue; // two branches: covered by the public menu tests
            }

            $this->get("/m/{$slug}/merkez/menu")
                ->assertOk()
                ->assertSee('data-theme="'.$theme.'"', escape: false);

            $this->get("/m/{$slug}/merkez/menu?lang=en")->assertOk()->assertSee('<html lang="en">', escape: false);
        }
    }

    public function test_venue_names_come_from_the_database(): void
    {
        $this->seed(DemoSeeder::class);
        $tenant = Tenant::query()->where('slug', 'noon')->sole();
        $tenant->translations()->where('locale', 'tr')->update(['name' => 'Öğle Kahvesi']);
        Cache::forget(MenuCacheKeys::slug('noon'));

        $this->get('/temalar')->assertSee('Öğle Kahvesi');
    }

    public function test_missing_or_offline_demos_get_no_link(): void
    {
        $this->seed(DemoSeeder::class);
        Tenant::query()->where('slug', 'petal-bakehouse')->sole()->forceFill(['status' => TenantStatus::Suspended])->save();
        Cache::forget(MenuCacheKeys::slug('petal-bakehouse'));
        config(['marketing.showcase.bistro' => 'does-not-exist']);
        $urls = app(MenuUrlGenerator::class);

        $this->get('/temalar')
            ->assertOk()
            ->assertSee(__('marketing.showcase.no_demo', [], 'tr'))
            ->assertDontSee($urls->tenant('petal-bakehouse'))
            ->assertDontSee($urls->tenant('does-not-exist'))
            ->assertSee($urls->tenant('noon'));
    }

    public function test_page_works_without_any_demo_data(): void
    {
        $this->get('/en/themes')
            ->assertOk()
            ->assertSee(__('marketing.showcase.no_demo', [], 'en'))
            ->assertSee('<link rel="canonical" href="'.url('/en/themes').'">', escape: false)
            ->assertSee('<link rel="alternate" hreflang="tr" href="'.url('/temalar').'">', escape: false);
    }

    public function test_nav_links_to_the_showcase_and_marks_it_current(): void
    {
        $html = $this->get('/temalar')->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/temalar'), '#').'"\s+aria-current="page"#', $html);
        $this->get('/')->assertSee('href="'.url('/temalar').'"', escape: false);
    }

    public function test_showcase_is_central_domain_only(): void
    {
        $this->get('http://yaman.'.config('tenancy.central_domain').'/temalar')->assertNotFound();
    }
}
