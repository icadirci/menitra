<?php

declare(strict_types=1);

namespace Tests\Feature\Translations;

use App\Support\MarketingUrl;
use App\Support\Money;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Menu (platform) locales vs interface locales: a menu-only language such as
 * Arabic gets the strings public menu pages need, but no marketing site,
 * mails or platform content.
 */
class MenuLocalesTest extends TestCase
{
    public function test_interface_and_rtl_locales_are_platform_locales(): void
    {
        $supported = array_keys(config('locales.supported'));

        $this->assertContains('ar', $supported);
        $this->assertSame([], array_diff(config('locales.interface'), $supported));
        $this->assertSame([], array_diff(config('locales.rtl'), $supported));
        $this->assertContains(config('locales.default'), config('locales.interface'));
    }

    public function test_every_platform_locale_has_the_public_menu_strings(): void
    {
        foreach (['menu', 'errors'] as $file) {
            $reference = array_keys(Arr::dot(require lang_path("en/{$file}.php")));

            foreach (array_keys(config('locales.supported')) as $locale) {
                $path = lang_path("{$locale}/{$file}.php");
                $this->assertFileExists($path);
                $this->assertSame($reference, array_keys(Arr::dot(require $path)), "lang/{$locale}/{$file}.php keys differ from en.");
            }
        }
    }

    public function test_menu_only_locales_get_no_marketing_site(): void
    {
        $this->assertSame(['tr', 'en'], array_keys(MarketingUrl::alternates('home')));

        $this->get('/ar')->assertNotFound();
        $this->get('/ar/features')->assertNotFound();
    }

    public function test_arabic_prices_use_western_digits(): void
    {
        $formatted = Money::fromMinor(125050, 'TRY')->format('ar');

        $this->assertStringContainsString('1,250.50', $formatted);
        $this->assertDoesNotMatchRegularExpression('/[\x{0660}-\x{0669}]/u', $formatted);
    }
}
