<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\BillingInterval;
use App\Enums\FaqPlacement;
use App\Models\Faq;
use App\Models\Plan;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;
use Tests\TestCase;

class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, int|null>  $limits
     */
    private function plan(string $code, ?int $monthly, ?int $yearly = null, array $limits = [], array $attributes = []): Plan
    {
        $plan = Plan::factory()->withLimits($limits)->create(['code' => $code, ...$attributes]);
        $plan->translations()->updateOrCreate(['locale' => 'tr'], ['name' => "TR {$code}", 'description' => "Açıklama {$code}"]);
        $plan->translations()->create(['locale' => 'en', 'name' => "EN {$code}"]);

        if ($monthly !== null) {
            $plan->prices()->create(['interval' => BillingInterval::Monthly, 'amount' => $monthly]);
        }
        if ($yearly !== null) {
            $plan->prices()->create(['interval' => BillingInterval::Yearly, 'amount' => $yearly]);
        }

        return $plan;
    }

    private function text(string $path): string
    {
        return preg_replace('/\s+/u', ' ', strip_tags($this->get($path)->assertOk()->getContent()));
    }

    public function test_pricing_page_renders_in_turkish_at_fiyatlar_and_english_at_en_pricing(): void
    {
        $this->get('/fiyatlar')
            ->assertOk()
            ->assertSee('<html lang="tr">', escape: false)
            ->assertSee(__('marketing.pricing_page.title', [], 'tr'))
            ->assertSee('<title>'.e(__('marketing.meta.pricing_title', [], 'tr')), escape: false);

        $this->get('/en/pricing')
            ->assertOk()
            ->assertSee('<html lang="en">', escape: false)
            ->assertSee(__('marketing.pricing_page.title', [], 'en'))
            ->assertDontSee(__('marketing.pricing_page.title', [], 'tr'));
    }

    public function test_canonical_and_hreflang_point_at_the_localised_paths(): void
    {
        $tr = url('/fiyatlar');
        $en = url('/en/pricing');

        foreach (['/fiyatlar' => $tr, '/en/pricing' => $en] as $path => $canonical) {
            $this->get($path)
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="tr" href="'.$tr.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="en" href="'.$en.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$tr.'">', escape: false);
        }
    }

    public function test_paths_are_not_mixed_between_locales_and_are_central_domain_only(): void
    {
        $this->get('/pricing')->assertNotFound();
        $this->get('/en/fiyatlar')->assertNotFound();

        $tenantHost = 'http://yaman.'.config('tenancy.central_domain');
        $this->get($tenantHost.'/fiyatlar')->assertNotFound();
        $this->get($tenantHost.'/en/pricing')->assertNotFound();
    }

    public function test_only_active_public_plans_with_a_monthly_price_are_listed_in_order(): void
    {
        $this->plan('pro', 49900, attributes: ['sort_order' => 2, 'is_featured' => true]);
        $this->plan('starter', 29900, attributes: ['sort_order' => 1]);
        $this->plan('hidden', 9900, attributes: ['is_public' => false]);
        $this->plan('retired', 9900, attributes: ['is_active' => false]);
        $this->plan('unpriced', null);

        $this->get('/fiyatlar')
            ->assertSeeInOrder(['TR starter', 'TR pro'])
            ->assertSee('Açıklama starter')
            ->assertSee(__('marketing.pricing_page.recommended', [], 'tr'))
            ->assertSee('₺299', escape: false)
            ->assertSee('₺499', escape: false)
            ->assertDontSee('TR hidden')
            ->assertDontSee('TR retired')
            ->assertDontSee('TR unpriced');

        $this->get('/en/pricing')->assertSee('EN pro');
    }

    public function test_plan_cta_links_to_registration_with_the_plan_and_shows_its_trial_days(): void
    {
        $this->plan('pro', 99900, attributes: ['trial_days' => 30]);
        $this->plan('basic', 49900, attributes: ['trial_days' => null]);

        $this->get('/fiyatlar')
            ->assertOk()
            ->assertSee('href="'.route('register', ['plan' => 'pro']).'"', false)
            ->assertSee('href="'.route('register', ['plan' => 'basic']).'"', false)
            ->assertSee(__('marketing.pricing_page.start_trial', ['days' => 30]))
            ->assertSee(__('marketing.pricing_page.start_trial', ['days' => config('subscriptions.trial_days')]));
    }

    public function test_yearly_prices_and_savings_are_computed_from_stored_prices(): void
    {
        // 12 × 499 = 5988 → 4790 saves 20 %; 12 × 299 = 3588 → 2990 saves 16 % (floored).
        $this->plan('starter', 29900, 299000, attributes: ['sort_order' => 1]);
        $this->plan('pro', 49900, 479000, attributes: ['sort_order' => 2]);

        $response = $this->get('/fiyatlar')->assertOk();

        $response
            ->assertSee('x-data="{ yearly: false }"', escape: false)
            ->assertSee('role="radiogroup"', escape: false)
            ->assertSee('₺4.790', escape: false)
            ->assertSee(__('marketing.pricing_page.billed_yearly', ['price' => '₺399,16'], 'tr'), escape: false)
            ->assertSee(__('marketing.pricing_page.billed_monthly_or_yearly', ['price' => '₺4.790'], 'tr'), escape: false)
            ->assertSee(__('marketing.pricing.yearly_saving', ['percent' => 20], 'tr'))
            ->assertSee(__('marketing.pricing.yearly_saving', ['percent' => 16], 'tr'))
            // Plans save different amounts → "up to" the largest saving.
            ->assertSee(__('marketing.pricing_page.saving_badge_up_to', ['percent' => 20], 'tr'));

        $this->get('/en/pricing')
            ->assertSee('Save up to 20%')
            ->assertSee(Money::fromMinor(479000)->format('en', omitZeroFraction: true), escape: false);
    }

    public function test_toggle_badge_shows_the_shared_saving_when_every_plan_saves_the_same(): void
    {
        $this->plan('starter', 10000, 100000);
        $this->plan('pro', 20000, 200000);

        $this->get('/fiyatlar')
            ->assertSee(__('marketing.pricing_page.saving_badge', ['percent' => 16], 'tr'))
            ->assertDontSee(__('marketing.pricing_page.saving_badge_up_to', ['percent' => 16], 'tr'));
    }

    public function test_plan_without_a_yearly_price_keeps_its_monthly_price(): void
    {
        $this->plan('starter', 29900);
        $this->plan('pro', 49900, 479000);

        $this->get('/fiyatlar')
            ->assertSee(__('marketing.pricing_page.monthly_only', [], 'tr'))
            ->assertSee(__('marketing.pricing_page.billed_monthly', [], 'tr'));
    }

    public function test_billing_toggle_is_hidden_when_no_plan_has_a_yearly_price(): void
    {
        $this->plan('starter', 29900);

        $this->get('/fiyatlar')
            ->assertOk()
            ->assertDontSee('role="radiogroup"', escape: false)
            ->assertDontSee(__('marketing.pricing_page.monthly_only', [], 'tr'), escape: false);
    }

    public function test_yearly_price_without_a_saving_shows_no_saving_badge(): void
    {
        $this->plan('starter', 10000, 120000);

        $this->get('/fiyatlar')
            ->assertSee('role="radiogroup"', escape: false)
            ->assertDontSee(__('marketing.pricing.yearly_saving', ['percent' => 0], 'tr'));
    }

    public function test_comparison_table_is_built_from_plan_limits(): void
    {
        $this->plan('starter', 29900, limits: [
            'max_locations' => 1, 'max_menus' => 3, 'max_items' => 1000, 'max_languages' => 2,
            'premium_themes' => 0, 'custom_domain' => 0,
        ], attributes: ['sort_order' => 1]);
        $this->plan('pro', 49900, limits: [
            'max_locations' => 5, 'max_menus' => null, 'max_items' => null, 'max_languages' => 2,
            'premium_themes' => 1, 'custom_domain' => 0,
        ], attributes: ['sort_order' => 2]);

        $response = $this->get('/fiyatlar')->assertOk();
        $content = $response->getContent();

        $response
            ->assertSee(__('marketing.pricing_page.compare.title', [], 'tr'))
            ->assertSeeInOrder([
                __('plans.features.max_locations', [], 'tr'), '1', '5',
                __('plans.features.max_menus', [], 'tr'), '3', __('plans.unlimited', [], 'tr'),
                __('plans.features.max_items', [], 'tr'), '1.000', __('plans.unlimited', [], 'tr'),
                __('plans.features.premium_themes', [], 'tr'),
                __('marketing.pricing_page.compare.not_included', [], 'tr'),
                __('marketing.pricing_page.compare.included', [], 'tr'),
            ]);

        // Not included in any plan (custom_domain = 0) or not limited at all (max_qr_codes): no row.
        $this->assertStringNotContainsString(__('plans.features.custom_domain', [], 'tr'), $content);
        $this->assertStringNotContainsString(__('plans.features.max_qr_codes', [], 'tr'), $content);

        $this->get('/en/pricing')->assertSee('1,000')->assertSee(__('plans.unlimited', [], 'en'));
    }

    public function test_plan_cards_list_highlights_from_included_limits(): void
    {
        $this->plan('starter', 29900, limits: [
            'max_locations' => 1, 'max_menus' => 3, 'max_items' => null, 'premium_themes' => 1, 'remove_branding' => 0,
        ]);

        $this->get('/fiyatlar')
            ->assertSee('1 şube')
            ->assertSee('3 menü')
            ->assertSee('Sınırsız ürün')
            ->assertSee('Tüm menü temaları (5)')
            ->assertDontSee(__('plans.features.remove_branding', [], 'tr'));

        $this->get('/en/pricing')
            ->assertSee('1 branch')
            ->assertSee('3 menus')
            ->assertSee('Unlimited products');
    }

    public function test_plans_without_premium_themes_advertise_the_basic_themes(): void
    {
        $this->plan('starter', 30000, limits: ['max_locations' => 1, 'premium_themes' => 0]);

        $this->get('/fiyatlar')->assertSee('2 temel menü teması')->assertDontSee('Tüm menü temaları');
        $this->get('/en/pricing')->assertSee('2 basic menu themes');
    }

    public function test_per_branch_pricing_is_shown_for_each_billing_period(): void
    {
        $plan = $this->plan('multi-branch', 70000, 560000, limits: ['max_locations' => null], attributes: ['included_locations' => 2]);
        $plan->prices()->where('interval', BillingInterval::Monthly)->update(['extra_location_amount' => 20000]);
        $plan->prices()->where('interval', BillingInterval::Yearly)->update(['extra_location_amount' => 160000]);
        $this->plan('pro', 50000, 400000);

        $text = $this->text('/fiyatlar');

        $this->assertStringContainsString('2 şube dahil', $text);
        $this->assertStringContainsString('Her ek şube +'.Money::fromMinor(20000)->format(omitZeroFraction: true).' / ay', $text);
        $this->assertStringContainsString('Her ek şube +'.Money::fromMinor(160000)->format(omitZeroFraction: true).' / yıl', $text);
        $this->assertStringContainsString('Sınırsız şube', $text);

        $this->get('/en/pricing')->assertSee('2 branches included')->assertSee('Each additional branch');
    }

    public function test_plans_without_per_branch_pricing_show_no_branch_note(): void
    {
        $this->plan('pro', 50000, 400000);

        $this->assertStringNotContainsString('Her ek şube', $this->text('/fiyatlar'));
    }

    public function test_unbuilt_features_are_never_advertised(): void
    {
        // Rows written around PlanService (e.g. directly in the database).
        $this->plan('pro', 49900, limits: ['max_menus' => 3, 'premium_themes' => 1, 'custom_domain' => 1, 'qr_analytics' => 1]);

        foreach (['/fiyatlar' => 'tr', '/en/pricing' => 'en', '/' => 'tr'] as $path => $locale) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString(__('plans.features.custom_domain', [], $locale), $content, $path);
            $this->assertStringNotContainsString(__('plans.features.qr_analytics', [], $locale), $content, $path);
        }

        $this->get('/fiyatlar')->assertSee(__('plans.features.premium_themes', [], 'tr'));
    }

    public function test_prices_are_marked_vat_inclusive(): void
    {
        $this->plan('starter', 29900);

        $this->get('/fiyatlar')->assertSee('Fiyatlara KDV dahildir.');
        $this->get('/en/pricing')->assertSee('Prices include VAT.');
        $this->get('/')->assertSee('Fiyatlara KDV dahildir.');
    }

    public function test_comparison_is_hidden_without_plan_limits(): void
    {
        $this->plan('starter', 29900);

        $this->get('/fiyatlar')->assertOk()->assertDontSee(__('marketing.pricing_page.compare.title', [], 'tr'));
    }

    public function test_empty_state_when_no_plan_is_priced(): void
    {
        $this->plan('starter', null);

        $this->get('/fiyatlar')
            ->assertOk()
            ->assertSee(__('marketing.pricing_page.empty', [], 'tr'))
            ->assertDontSee('role="radiogroup"', escape: false)
            ->assertDontSee(__('marketing.pricing_page.compare.title', [], 'tr'));
    }

    public function test_only_published_pricing_faqs_are_shown(): void
    {
        Faq::factory()->state(['placement' => FaqPlacement::Pricing])->withTranslations([
            'tr' => ['question' => 'Nasıl ödeme yaparım?', 'answer' => 'Kartla'],
            'en' => ['question' => 'How do I pay?', 'answer' => 'By card'],
        ])->create();
        Faq::factory()->state(['placement' => FaqPlacement::Pricing])->unpublished()->withTranslations(['tr' => ['question' => 'Taslak fiyat sorusu', 'answer' => 'x']])->create();
        Faq::factory()->state(['placement' => FaqPlacement::Home])->withTranslations(['tr' => ['question' => 'Ana sayfa sorusu', 'answer' => 'x']])->create();

        $this->get('/fiyatlar')
            ->assertSee(__('marketing.pricing_page.faq.title', [], 'tr'))
            ->assertSee('Nasıl ödeme yaparım?')
            ->assertDontSee('Taslak fiyat sorusu')
            ->assertDontSee('Ana sayfa sorusu');

        $this->get('/en/pricing')->assertSee('How do I pay?');
    }

    public function test_faq_section_is_hidden_without_pricing_faqs(): void
    {
        Faq::factory()->state(['placement' => FaqPlacement::Home])->withTranslations(['tr' => ['question' => 'Ana sayfa sorusu', 'answer' => 'x']])->create();

        $this->get('/fiyatlar')->assertDontSee(__('marketing.pricing_page.faq.title', [], 'tr'));
    }

    public function test_nav_footer_and_home_link_pricing_to_the_page_in_the_current_locale(): void
    {
        $this->plan('starter', 29900);

        $trLink = 'href="'.url('/fiyatlar').'"';
        $enLink = 'href="'.url('/en/pricing').'"';

        // Desktop nav, mobile nav, footer, hero button and "compare all plans" under the pricing preview.
        $this->assertSame(5, substr_count($this->get('/')->getContent(), $trLink));
        $this->assertSame(5, substr_count($this->get('/en')->getContent(), $enLink));
        $this->get('/')->assertDontSee('#pricing"', escape: false)->assertSee(__('marketing.pricing.compare_all', [], 'tr'));

        // Features page: nav, mobile nav, footer, CTA.
        $this->assertSame(4, substr_count($this->get('/ozellikler')->getContent(), $trLink));
    }

    public function test_pricing_nav_link_is_marked_as_the_current_page(): void
    {
        $current = fn (string $path): string => '/href="'.preg_quote(url($path), '/').'"\s+aria-current="page"/';

        $content = $this->get('/fiyatlar')->getContent();
        $this->assertMatchesRegularExpression($current('/fiyatlar'), $content);
        $this->assertDoesNotMatchRegularExpression($current('/ozellikler'), $content);
        $this->assertDoesNotMatchRegularExpression($current('/fiyatlar'), $this->get('/')->getContent());
    }

    public function test_language_switcher_links_to_the_pricing_page_in_the_other_locale(): void
    {
        $this->get('/fiyatlar')->assertSee('href="'.url('/en/pricing').'"', escape: false);
        $this->get('/en/pricing')->assertSee('href="'.url('/fiyatlar').'"', escape: false);
    }

    public function test_page_uses_the_marketing_bundle_without_livewire(): void
    {
        SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest = false;

        $this->get('/fiyatlar')
            ->assertOk()
            ->assertDontSee('livewire', escape: false)
            ->assertDontSee('menu-base', escape: false);
    }

    public function test_pricing_copy_has_the_same_keys_in_every_locale(): void
    {
        $locales = array_keys(config('locales.supported'));

        foreach (['marketing.pricing_page', 'plans.allowance', 'plans.allowance_unlimited'] as $key) {
            $reference = array_keys(Arr::dot(__($key, [], $locales[0])));

            foreach ($locales as $locale) {
                $copy = __($key, [], $locale);
                $this->assertIsArray($copy, "{$key} is missing in {$locale}.");
                $this->assertSame($reference, array_keys(Arr::dot($copy)), "{$key} keys differ in {$locale}.");
            }
        }

        foreach ($locales as $locale) {
            $this->assertIsString(__('marketing.meta.pricing_title', [], $locale));
            $this->assertIsString(__('marketing.meta.pricing_description', [], $locale));
        }
    }

    public function test_reserved_slugs_include_the_localised_pricing_paths(): void
    {
        $this->assertContains('fiyatlar', config('tenancy.reserved_slugs'));
        $this->assertContains('pricing', config('tenancy.reserved_slugs'));
    }
}
