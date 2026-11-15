<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\BillingInterval;
use App\Enums\FaqPlacement;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $code, ?int $monthly, array $attributes = [], ?int $yearly = null): Plan
    {
        $plan = Plan::factory()->create(['code' => $code, ...$attributes]);
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

    public function test_home_renders_in_turkish_at_root_and_english_under_en(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="tr">', escape: false)
            ->assertSee(__('marketing.hero.title', [], 'tr'))
            ->assertSee('hreflang="en"', escape: false);

        $this->get('/en')
            ->assertOk()
            ->assertSee('<html lang="en">', escape: false)
            ->assertSee(__('marketing.hero.title', [], 'en'))
            ->assertDontSee(__('marketing.hero.title', [], 'tr'));
    }

    public function test_home_is_central_domain_only(): void
    {
        $this->get('http://yaman.'.config('tenancy.central_domain').'/')->assertNotFound();
        $this->get('http://yaman.'.config('tenancy.central_domain').'/en')->assertNotFound();
    }

    public function test_only_published_home_faqs_are_shown_in_the_current_locale(): void
    {
        Faq::factory()->withTranslations([
            'tr' => ['question' => 'Yayındaki soru', 'answer' => 'Cevap'],
            'en' => ['question' => 'Published question', 'answer' => 'Answer'],
        ])->create();
        Faq::factory()->unpublished()->withTranslations(['tr' => ['question' => 'Taslak soru', 'answer' => 'x']])->create();
        Faq::factory()->state(['placement' => FaqPlacement::Pricing])->withTranslations(['tr' => ['question' => 'Fiyat sorusu', 'answer' => 'x']])->create();
        Faq::factory()->withTranslations(['tr' => ['question' => 'Sadece Türkçe soru', 'answer' => 'x']])->create();

        $this->get('/')
            ->assertSee('Yayındaki soru')
            ->assertDontSee('Taslak soru')
            ->assertDontSee('Fiyat sorusu');

        $this->get('/en')
            ->assertSee('Published question')
            ->assertSee('Sadece Türkçe soru', escape: false); // falls back to the platform default locale
    }

    public function test_testimonials_section_is_hidden_without_published_testimonials(): void
    {
        Testimonial::factory()->unpublished()->withTranslations(['tr' => ['quote' => 'Gizli yorum']])->create();

        $this->get('/')
            ->assertDontSee(__('marketing.testimonials.title', [], 'tr'))
            ->assertDontSee('Gizli yorum');

        Testimonial::factory()->state(['author_name' => 'Mert Karaca'])->withTranslations(['tr' => ['quote' => 'Harika bir ürün', 'author_role' => 'Müdür']])->create();

        $this->get('/')
            ->assertSee(__('marketing.testimonials.title', [], 'tr'))
            ->assertSee('Harika bir ürün')
            ->assertSee('Mert Karaca')
            ->assertSee('MK');
    }

    public function test_pricing_shows_only_active_public_plans_with_a_monthly_price(): void
    {
        $this->plan('starter', 29900, ['sort_order' => 1]);
        $this->plan('pro', 49900, ['sort_order' => 2, 'is_featured' => true], yearly: 479000);
        $this->plan('hidden', 9900, ['is_public' => false]);
        $this->plan('retired', 9900, ['is_active' => false]);
        $this->plan('unpriced', null);

        $response = $this->get('/')->assertOk();

        $response->assertSeeInOrder(['TR starter', 'TR pro'])
            ->assertSee('Açıklama starter')
            ->assertSee(__('marketing.pricing.most_popular', [], 'tr'))
            ->assertSee('₺499', escape: false)
            ->assertSee(__('marketing.pricing.yearly_saving', ['percent' => 20], 'tr'))
            ->assertDontSee('TR hidden')
            ->assertDontSee('TR retired')
            ->assertDontSee('TR unpriced');

        $this->get('/en')->assertSee('EN pro')->assertSee('Save 20%');
    }

    public function test_pricing_section_is_hidden_when_no_plan_is_priced(): void
    {
        $this->plan('starter', null);

        $this->get('/')->assertOk()->assertDontSee('id="pricing"', escape: false);
    }
}
