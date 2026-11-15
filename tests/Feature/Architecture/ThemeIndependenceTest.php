<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

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
use Illuminate\Support\Facades\View;
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
            if (! View::exists($theme->view())) {
                continue; // Built in Phase 7; ThemeRenderer falls back to classic.
            }

            $settings = ThemeSettings::defaults($theme);
            $html = view($theme->view(), ['menu' => $snapshot, 'live' => $live, 'theme' => $settings])->render();
            $location = view($theme->locationView(), ['menu' => $snapshot->withMenu($snapshot->menu), 'live' => $live, 'theme' => $settings])->render();

            foreach (['Türk Kahvesi', '₺150', 'Duble', 'Şeker tercihi', 'Glutensiz', 'Süt', __('menu.sold_out'), __('menu.popular'), __('menu.featured'), '?lang=en'] as $expected) {
                $this->assertStringContainsString($expected, $html, "{$theme->value} doesn't render [{$expected}].");
            }
            $this->assertStringContainsString('08:00–22:00', $location);
            $rendered++;
        }

        $this->assertGreaterThanOrEqual(1, $rendered);
        $this->assertSame(0, $queries);
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
