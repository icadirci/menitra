<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Enums\MenuTheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Bundle boundaries: public site → app.css/app.js; QR menus → menu-base.css,
 * one theme stylesheet and menu.js; Filament panels → none of ours.
 */
class FrontendBundlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_menu_theme_has_a_stylesheet_and_a_vite_entry(): void
    {
        $themes = array_column(MenuTheme::cases(), 'value');

        preg_match('/const menuThemes = \[([^\]]*)\]/', File::get(base_path('vite.config.js')), $match);
        $this->assertNotEmpty($match, 'vite.config.js must declare the menuThemes list.');
        preg_match_all("/'([a-z]+)'/", $match[1], $configured);

        $this->assertEqualsCanonicalizing($themes, $configured[1], 'vite.config.js menuThemes must match MenuTheme.');

        $files = array_map(fn (string $path) => basename($path, '.css'), File::glob(resource_path('css/themes/*.css')));
        $this->assertEqualsCanonicalizing($themes, $files, 'resources/css/themes must contain exactly one file per MenuTheme.');
    }

    public function test_menu_bundle_stays_free_of_marketing_code(): void
    {
        $menuJs = File::get(resource_path('js/menu.js'));

        $this->assertDoesNotMatchRegularExpression("/^\s*import\s.*(bootstrap|axios)/m", $menuJs);
        $this->assertStringContainsString('startAlpine', $menuJs);
        $this->assertStringNotContainsString('@import', File::get(resource_path('css/themes/classic.css')));
    }

    public function test_menu_css_scans_only_menu_markup(): void
    {
        $this->assertStringContainsString("@import 'tailwindcss' source(none);", File::get(resource_path('css/menu-base.css')));

        $appCss = File::get(resource_path('css/app.css'));
        $this->assertStringContainsString("@source not '../views/themes';", $appCss);
        $this->assertStringContainsString("@source not '../js/menu.js';", $appCss);
    }

    public function test_public_pages_load_only_the_public_bundle_and_panels_load_none(): void
    {
        $hot = storage_path('framework/testing/vite-bundles.hot');
        File::ensureDirectoryExists(dirname($hot));
        File::put($hot, 'http://vite.test');

        try {
            $this->withVite();
            app(Vite::class)->useHotFile($hot);

            $this->get('/register')
                ->assertOk()
                ->assertSee('http://vite.test/resources/js/app.js', escape: false)
                ->assertDontSee('resources/js/menu.js', escape: false)
                ->assertDontSee('menu-base.css', escape: false);

            $this->get('/admin/login')->assertOk()->assertDontSee('vite.test', escape: false);
            $this->get('/app/login')->assertOk()->assertDontSee('vite.test', escape: false);
            $this->actingAs(User::factory()->superAdmin()->create())
                ->get('/admin')
                ->assertOk()
                ->assertDontSee('vite.test', escape: false);
        } finally {
            File::delete($hot);
        }
    }
}
