<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;
use Tests\TestCase;

class FeaturesPageTest extends TestCase
{
    public function test_features_page_renders_in_turkish_at_ozellikler_and_english_at_en_features(): void
    {
        $this->get('/ozellikler')
            ->assertOk()
            ->assertSee('<html lang="tr">', escape: false)
            ->assertSee(__('marketing.features_page.title', [], 'tr'))
            ->assertSee(__('marketing.features_page.details.items.0.title', [], 'tr'))
            ->assertSee(__('marketing.features_page.team.title', [], 'tr'))
            ->assertSee('<title>'.e(__('marketing.meta.features_title', [], 'tr')), escape: false);

        $this->get('/en/features')
            ->assertOk()
            ->assertSee('<html lang="en">', escape: false)
            ->assertSee(__('marketing.features_page.title', [], 'en'))
            ->assertDontSee(__('marketing.features_page.title', [], 'tr'));
    }

    public function test_canonical_and_hreflang_point_at_the_localised_paths(): void
    {
        $tr = url('/ozellikler');
        $en = url('/en/features');

        foreach (['/ozellikler' => $tr, '/en/features' => $en] as $path => $canonical) {
            $this->get($path)
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="tr" href="'.$tr.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="en" href="'.$en.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$tr.'">', escape: false);
        }
    }

    public function test_paths_are_not_mixed_between_locales(): void
    {
        $this->get('/features')->assertNotFound();
        $this->get('/en/ozellikler')->assertNotFound();
    }

    public function test_every_localised_marketing_path_is_a_reserved_tenant_slug(): void
    {
        $reserved = config('tenancy.reserved_slugs');
        $locales = config('locales.interface');

        foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
            if (! str_starts_with($name, 'marketing.')) {
                continue;
            }

            $segment = collect(explode('/', $route->uri()))
                ->reject(fn (string $part) => $part === '' || in_array($part, $locales, true))
                ->first();

            if ($segment !== null) {
                $this->assertContains($segment, $reserved, "Marketing path “{$segment}” ({$name}) must be in tenancy.reserved_slugs.");
            }
        }
    }

    public function test_features_page_is_central_domain_only(): void
    {
        $tenantHost = 'http://yaman.'.config('tenancy.central_domain');

        $this->get($tenantHost.'/ozellikler')->assertNotFound();
        $this->get($tenantHost.'/en/features')->assertNotFound();
    }

    public function test_nav_and_footer_link_features_to_the_page_in_the_current_locale(): void
    {
        $trLink = 'href="'.url('/ozellikler').'"';
        $enLink = 'href="'.url('/en/features').'"';

        // Desktop nav, mobile nav and footer.
        $this->assertSame(3, substr_count($this->get('/')->getContent(), $trLink));
        $this->assertSame(3, substr_count($this->get('/en')->getContent(), $enLink));
        $this->get('/')->assertDontSee('#features', escape: false);
    }

    public function test_features_nav_link_is_marked_as_the_current_page_only_on_the_features_page(): void
    {
        $this->get('/ozellikler')->assertSee('aria-current="page"', escape: false);
        $this->get('/')->assertDontSee('aria-current="page"', escape: false);
    }

    public function test_language_switcher_links_to_the_features_page_in_the_other_locale(): void
    {
        $this->get('/ozellikler')->assertSee('href="'.url('/en/features').'"', escape: false);
        $this->get('/en/features')->assertSee('href="'.url('/ozellikler').'"', escape: false);
    }

    public function test_page_uses_the_marketing_bundle_without_livewire(): void
    {
        // Livewire keeps this flag in a static; a panel test earlier in the same
        // PHPUnit process would otherwise leak it (each FPM request starts clean).
        SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest = false;

        $this->get('/ozellikler')
            ->assertOk()
            ->assertDontSee('livewire', escape: false)
            ->assertDontSee('menu-base', escape: false);
    }

    public function test_features_copy_has_the_same_keys_in_every_locale(): void
    {
        $locales = config('locales.interface');
        $reference = array_keys(Arr::dot(__('marketing.features_page', [], $locales[0])));

        foreach ($locales as $locale) {
            $copy = __('marketing.features_page', [], $locale);
            $this->assertIsArray($copy, "lang/{$locale}/marketing.php must define features_page.");
            $this->assertSame($reference, array_keys(Arr::dot($copy)), "features_page keys differ in {$locale}.");
            $this->assertIsString(__('marketing.meta.features_title', [], $locale));
        }
    }

    /**
     * The design mock-up advertises things the product doesn't provide (PDF and
     * print templates, table codes, 12 layouts, languages beyond tr/en/ar, custom
     * domains, automatic image compression). They must not reach the page.
     */
    public function test_page_does_not_advertise_unsupported_features(): void
    {
        $unsupported = [
            'tr' => ['PDF', 'şablon', 'Masa 12', '12 tema', 'Almanca', 'Rusça', 'alan adı', 'sıkıştır'],
            'en' => ['PDF', 'template', 'Table 12', '12 layouts', 'German', 'Russian', 'domain', 'compress'],
        ];

        foreach (['tr' => '/ozellikler', 'en' => '/en/features'] as $locale => $path) {
            $content = mb_strtolower(strip_tags($this->get($path)->getContent()));

            foreach ($unsupported[$locale] as $phrase) {
                $this->assertStringNotContainsString(mb_strtolower($phrase), $content, "{$path} mentions “{$phrase}”.");
            }
        }
    }
}
