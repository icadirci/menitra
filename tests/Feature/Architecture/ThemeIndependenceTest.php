<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Enums\MenuFontPair;
use App\Enums\MenuTheme;
use App\Support\Menu\Data\CategoryData;
use App\Support\Menu\Data\ExtraData;
use App\Support\Menu\Data\ExtraGroupData;
use App\Support\Menu\Data\HoursData;
use App\Support\Menu\Data\ItemData;
use App\Support\Menu\Data\LabelData;
use App\Support\Menu\Data\LocaleData;
use App\Support\Menu\Data\LocationInfo;
use App\Support\Menu\Data\MenuData;
use App\Support\Menu\Data\MenuSnapshot;
use App\Support\Menu\Data\MenuSummary;
use App\Support\Menu\Data\PriceData;
use App\Support\Menu\Data\SpecialHoursData;
use App\Support\Menu\Data\TenantBrand;
use App\Support\Menu\Data\VariantData;
use App\Support\Menu\LiveMenuState;
use App\Support\Menu\ThemeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Themes are presentation only (docs/architecture/themes.md § Enforcing independence).
 */
class ThemeIndependenceTest extends TestCase
{
    private const array FORBIDDEN = ['App\\Models', 'App\\Services', 'DB::', 'Cache::', 'auth(', 'request(', '@php', '{!!'];

    public function test_theme_views_use_no_data_sources_or_raw_output(): void
    {
        $files = File::allFiles(resource_path('views/themes'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            foreach (self::FORBIDDEN as $needle) {
                $this->assertStringNotContainsString($needle, $file->getContents(), "{$file->getRelativePathname()} uses [{$needle}].");
            }
        }
    }

    public function test_every_built_theme_renders_the_fake_snapshot_without_queries(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $snapshot = $this->snapshot();
        $live = LiveMenuState::for($snapshot->location, CarbonImmutable::parse('2026-11-16 10:00', 'Europe/Istanbul'));
        $rendered = 0;

        foreach (MenuTheme::cases() as $theme) {
            $this->assertTrue($theme->isBuilt(), "{$theme->value} has no views (all five are built since Phase 7).");

            $settings = ThemeSettings::defaults($theme);
            $html = view($theme->view(), ['menu' => $snapshot, 'live' => $live, 'theme' => $settings])->render();
            $location = view($theme->locationView(), ['menu' => $snapshot->withMenu($snapshot->menu), 'live' => $live, 'theme' => $settings])->render();

            foreach (['Türk Kahvesi', '₺150', 'Duble', 'Şeker tercihi', 'Glutensiz', 'Süt', __('menu.sold_out'), __('menu.popular'), __('menu.featured'), '?lang=en'] as $expected) {
                $this->assertStringContainsString($expected, $html, "{$theme->value} doesn't render [{$expected}].");
            }
            $this->assertStringContainsString('08:00–22:00', $location);
            $rendered++;
        }

        $this->assertSame(count(MenuTheme::cases()), $rendered);
        $this->assertSame(0, $queries);
    }

    public function test_every_theme_renders_with_its_layout_options_switched_and_images(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $snapshot = $this->snapshot();
        $live = LiveMenuState::for($snapshot->location, CarbonImmutable::parse('2026-11-16 10:00', 'Europe/Istanbul'));

        foreach (MenuTheme::cases() as $theme) {
            $defaults = $theme->defaults();
            $flipped = $defaults['layout'];

            foreach ($flipped as $name => $value) {
                $flipped[$name] = is_bool($value) ? ! $value : (array_values(array_diff($theme->layoutChoices()[$name], [$value]))[0] ?? $value);
            }

            foreach ([$defaults['layout'], $flipped] as $layout) {
                $settings = ThemeSettings::fromStored($theme, ['layout' => $layout] + $defaults);
                $html = view($theme->view(), ['menu' => $snapshot, 'live' => $live, 'theme' => $settings])->render();

                $this->assertStringContainsString('data-theme="'.$theme->value.'"', $html);
                $this->assertStringContainsString('Türk Kahvesi', $html);
            }

            // Featured strip follows show_featured where the theme offers it.
            if (array_key_exists('show_featured', $defaults['layout'])) {
                $off = ThemeSettings::fromStored($theme, ['layout' => ['show_featured' => false] + $defaults['layout']] + $defaults);
                $html = view($theme->view(), ['menu' => $snapshot, 'live' => $live, 'theme' => $off])->render();
                $this->assertStringNotContainsString('-featured-title', $html, "{$theme->value} ignores show_featured.");
            }
        }

        $this->assertSame(0, $queries);
    }

    public function test_every_theme_offers_the_order_list_only_when_enabled(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $snapshot = $this->snapshot();
        $live = LiveMenuState::for($snapshot->location, CarbonImmutable::parse('2026-11-16 10:00', 'Europe/Istanbul'));

        foreach (MenuTheme::cases() as $theme) {
            $defaults = $theme->defaults();
            $this->assertFalse($defaults['layout']['cart'], "{$theme->value} must ship with the order list off.");

            // Off (also for settings saved before the option existed): no order list, but the dock is there.
            $off = view($theme->view(), ['menu' => $snapshot, 'live' => $live, 'theme' => ThemeSettings::fromStored($theme, ['layout' => array_diff_key($defaults['layout'], ['cart' => true])] + $defaults)])->render();
            $this->assertStringContainsString('menu-dock', $off, "{$theme->value} has no navigation dock.");
            $this->assertStringContainsString(__('menu.menus'), $off, "{$theme->value} offers no way back to the branch's menus.");
            $this->assertStringNotContainsString('data-cart=', $off);
            $this->assertStringNotContainsString('menu-cart-add', $off);
            $this->assertStringNotContainsString('dlg-cart', $off);
            $this->assertStringContainsString('menuItem(null)', $off);

            $on = view($theme->view(), ['menu' => $snapshot, 'live' => $live, 'theme' => ThemeSettings::fromStored($theme, ['layout' => ['cart' => true] + $defaults['layout']] + $defaults)])->render();
            $this->assertStringContainsString('data-cart="http://konak.localhost/moda"', $on, "{$theme->value} ignores the cart option.");
            $this->assertStringContainsString('data-currency="TRY"', $on);
            $this->assertStringContainsString('dlg-cart', $on);
            $this->assertStringContainsString(__('menu.cart_done'), $on);
            // Variants become a choice; only the available item can be added (Sütlaç is sold out).
            $this->assertStringContainsString("variant = 'v2'", $on);
            $this->assertSame(1, substr_count($on, 'menu-cart-add__button'), "{$theme->value} offers sold-out items for the order list.");
        }

        $this->assertSame(0, $queries);
    }

    public function test_every_theme_links_its_logo_to_the_branch_home(): void
    {
        $snapshot = $this->snapshot();
        $live = LiveMenuState::for($snapshot->location, CarbonImmutable::parse('2026-11-16 10:00', 'Europe/Istanbul'));
        $location = new MenuSnapshot($snapshot->version, $snapshot->locale, $snapshot->availableLocales, $snapshot->brand, $snapshot->location, $snapshot->menus);

        foreach (MenuTheme::cases() as $theme) {
            $settings = ThemeSettings::defaults($theme);

            foreach ([$theme->view() => $snapshot, $theme->locationView() => $location] as $view => $data) {
                $html = view($view, ['menu' => $data, 'live' => $live, 'theme' => $settings])->render();

                $this->assertMatchesRegularExpression('/<a class="menu-home[^"]*"\s+href="http:\/\/konak\.localhost\/moda\?lang=tr"/', $html, "{$view} has no home link on its logo.");
                $this->assertStringContainsString(__('menu.home_link', ['name' => 'Kahve Konağı']), $html);
            }
        }

        // A branch with a single menu: home is that menu (the location URL renders it too).
        $single = new MenuSnapshot($snapshot->version, $snapshot->locale, $snapshot->availableLocales, $snapshot->brand, $snapshot->location, [$snapshot->menus[0]], $snapshot->menu);
        $html = view(MenuTheme::Classic->view(), ['menu' => $single, 'live' => $live, 'theme' => ThemeSettings::defaults(MenuTheme::Classic)])->render();
        $this->assertStringContainsString('href="http://konak.localhost/moda/menu?lang=tr"', $html);
    }

    public function test_cart_payload_carries_only_ids_names_and_prices(): void
    {
        $item = $this->snapshot()->menu->categories[0]->items[0];

        $this->assertSame([
            'id' => $item->publicId,
            'name' => 'Türk Kahvesi',
            'price' => null,
            'variants' => [
                ['id' => 'v1', 'name' => 'Tek', 'price' => 15000, 'default' => true, 'available' => true],
                ['id' => 'v2', 'name' => 'Duble', 'price' => 21000, 'default' => false, 'available' => true],
            ],
            'groups' => [
                ['name' => 'Şeker tercihi', 'min' => 0, 'extras' => [
                    ['id' => 'e1', 'name' => 'Sade', 'delta' => 0],
                    ['id' => 'e2', 'name' => 'Orta', 'delta' => 0],
                ]],
            ],
        ], $item->cartPayload());
    }

    public function test_every_theme_offers_category_cards_and_keeps_the_list_by_default(): void
    {
        $snapshot = $this->snapshot();
        $live = LiveMenuState::for($snapshot->location, CarbonImmutable::parse('2026-11-16 10:00', 'Europe/Istanbul'));

        foreach (MenuTheme::cases() as $theme) {
            $defaults = $theme->defaults();
            $this->assertSame('list', $defaults['layout']['category_layout'], "{$theme->value} must default to the list.");

            // Settings saved before the option existed render as the list.
            $list = view($theme->view(), ['menu' => $snapshot, 'live' => $live, 'theme' => ThemeSettings::fromStored($theme, ['layout' => array_diff_key($defaults['layout'], ['category_layout' => true])] + $defaults)])->render();
            $this->assertStringNotContainsString('menu-tiles', $list, "{$theme->value} shows category cards in list mode.");
            $this->assertStringContainsString('menuPage(false)', $list);

            $tiles = view($theme->view(), ['menu' => $snapshot, 'live' => $live, 'theme' => ThemeSettings::fromStored($theme, ['layout' => ['category_layout' => 'tiles'] + $defaults['layout']] + $defaults)])->render();
            $this->assertStringContainsString('menuPage(true)', $tiles);
            $this->assertStringContainsString('data-tile="c1"', $tiles, "{$theme->value} ignores category_layout.");
            $this->assertStringContainsString(__('menu.all_categories'), $tiles);
            // Items are still rendered (shown per category on the client).
            $this->assertStringContainsString('Türk Kahvesi', $tiles);
        }
    }

    public function test_every_theme_stylesheet_declares_the_fonts_it_offers(): void
    {
        $families = [
            'young_serif_figtree' => ['Young Serif', 'Figtree'],
            'bricolage_inter' => ['Bricolage Grotesque', 'Inter'],
            'inter' => ['Inter'],
            'figtree' => ['Figtree'],
            'system' => [],
        ];

        foreach (MenuTheme::cases() as $theme) {
            $css = File::get(resource_path("css/themes/{$theme->value}.css"));

            $this->assertStringContainsString("[data-theme='{$theme->value}']", $css);
            $this->assertStringContainsString("@reference '../menu-base.css';", $css);
            $this->assertContains(MenuFontPair::from($theme->defaults()['font_pair']), $theme->fontPairs());

            foreach ($theme->fontPairs() as $pair) {
                $this->assertArrayHasKey($pair->value, $families, "Map the families of {$pair->value} in this test.");

                foreach ($families[$pair->value] as $family) {
                    $this->assertStringContainsString("font-family: '{$family}'", $css, "{$theme->value}.css lacks @font-face for {$family}.");
                }
            }
        }
    }

    public function test_live_state_handles_midnight_and_special_days(): void
    {
        $location = $this->snapshot()->location;

        $this->assertTrue(LiveMenuState::for($location, CarbonImmutable::parse('2026-11-16 10:00', 'Europe/Istanbul'))->isOpenNow());
        $this->assertFalse(LiveMenuState::for($location, CarbonImmutable::parse('2026-11-16 23:00', 'Europe/Istanbul'))->isOpenNow());
        // Saturday 18:00–02:00 → still open early on Sunday.
        $this->assertTrue(LiveMenuState::for($location, CarbonImmutable::parse('2026-11-22 01:30', 'Europe/Istanbul'))->isOpenNow());
        // Special day: closed all day.
        $this->assertFalse(LiveMenuState::for($location, CarbonImmutable::parse('2026-11-17 12:00', 'Europe/Istanbul'))->isOpenNow());
    }

    private function snapshot(): MenuSnapshot
    {
        $price = fn (int $minor): PriceData => new PriceData($minor, '₺'.intdiv($minor, 100));
        $item = fn (string $name, array $overrides = []): ItemData => new ItemData(...array_merge([
            'publicId' => strtolower(substr(md5($name), 0, 26)),
            'name' => $name,
            'description' => 'Közde pişer.',
            'imageUrl' => null,
            'price' => $price(15000),
            'fromPrice' => null,
            'isFeatured' => false,
            'isPopular' => false,
            'isAvailable' => true,
            'soldOutIndefinitely' => false,
            'soldOutUntil' => null,
            'calories' => 120,
            'preparationMinutes' => 8,
            'allergens' => [new LabelData('milk', 'Süt')],
            'dietaryLabels' => [new LabelData('gluten_free', 'Glutensiz')],
            'variants' => [],
            'extraGroups' => [],
        ], $overrides));

        $turkish = $item('Türk Kahvesi', [
            'isFeatured' => true,
            'isPopular' => true,
            'price' => null,
            'fromPrice' => $price(15000),
            'variants' => [new VariantData('v1', 'Tek', $price(15000), true, true), new VariantData('v2', 'Duble', $price(21000), false, true)],
            'extraGroups' => [new ExtraGroupData('Şeker tercihi', 'single', 0, 1, false, [new ExtraData('e1', 'Sade', null, true), new ExtraData('e2', 'Orta', null, true)])],
        ]);

        $weekly = array_map(fn (int $day): HoursData => new HoursData($day, '08:00', '22:00'), range(1, 5));
        $weekly[] = new HoursData(6, '18:00', '02:00');

        $location = new LocationInfo('moda', 'Moda', null, 'Moda Cd. 118', '+90 216 000 00 00', null, null, null,
            'Europe/Istanbul', null, null, 'http://konak.localhost/moda', $weekly, [new SpecialHoursData('2026-11-17', true, null, null)]);

        return new MenuSnapshot(
            version: '1.1',
            locale: 'tr',
            availableLocales: [new LocaleData('tr', 'Türkçe'), new LocaleData('en', 'English')],
            brand: new TenantBrand('Kahve Konağı', 'Közde Türk kahvesi', 'Açıklama', null, null, true),
            location: $location,
            menus: [
                new MenuSummary(1, 'menu', 'Ana Menü', null, 'all_day', 'Gün boyu', 'http://konak.localhost/moda/menu', []),
                new MenuSummary(2, 'kahvalti', 'Kahvaltı', null, 'breakfast', 'Kahvaltı', 'http://konak.localhost/moda/kahvalti', [new HoursData(1, '08:00', '12:00')]),
            ],
            menu: new MenuData('menu', 'Ana Menü', null, 'all_day', [
                new CategoryData(1, 'c1', 'Kahveler', null, null, [$turkish, $item('Sütlaç', ['soldOutIndefinitely' => true])]),
            ], [$turkish]),
        );
    }
}
