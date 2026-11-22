<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\ContactSubject;
use App\Enums\FaqPlacement;
use App\Http\Requests\Marketing\ContactRequest;
use App\Mail\ContactMessageMail;
use App\Models\Faq;
use App\Models\SiteSetting;
use App\Services\Contact\ContactMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array<string, string|null>>  $translations
     */
    private function settings(array $attributes = [], array $translations = []): SiteSetting
    {
        $settings = SiteSetting::current();
        $settings->update($attributes);

        foreach ($translations as $locale => $values) {
            $settings->translations()->create(['locale' => $locale, ...$values]);
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Ayşe Demir',
            'email' => 'ayse@example.com',
            'subject' => ContactSubject::Setup->value,
            'message' => 'Menümüzü Menitra’ya taşımak istiyoruz, yardımcı olur musunuz?',
            ...$overrides,
        ];
    }

    public function test_contact_page_renders_in_turkish_at_iletisim_and_english_at_en_contact(): void
    {
        $this->get('/iletisim')
            ->assertOk()
            ->assertSee('<html lang="tr">', escape: false)
            ->assertSee(__('marketing.contact_page.title', [], 'tr'))
            ->assertSee('<title>'.e(__('marketing.meta.contact_title', [], 'tr')), escape: false)
            ->assertSee('action="'.url('/iletisim').'"', escape: false)
            ->assertSee('name="_token"', escape: false)
            ->assertSee(ContactSubject::Billing->label('tr'));

        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('<html lang="en">', escape: false)
            ->assertSee(__('marketing.contact_page.title', [], 'en'))
            ->assertSee('action="'.url('/en/contact').'"', escape: false)
            ->assertDontSee(__('marketing.contact_page.title', [], 'tr'));
    }

    public function test_canonical_hreflang_and_language_switcher_use_the_localised_paths(): void
    {
        $this->get('/iletisim')
            ->assertSee('<link rel="canonical" href="'.url('/iletisim').'">', escape: false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/contact').'">', escape: false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/iletisim').'">', escape: false);

        $this->get('/en/contact')->assertSee('href="'.url('/iletisim').'"', escape: false);
    }

    public function test_contact_page_is_central_domain_only(): void
    {
        $tenantHost = 'http://yaman.'.config('tenancy.central_domain');

        $this->get($tenantHost.'/iletisim')->assertNotFound();
        $this->get($tenantHost.'/en/contact')->assertNotFound();
        $this->post($tenantHost.'/iletisim', $this->payload())->assertNotFound();
    }

    public function test_page_uses_the_marketing_bundle_without_livewire(): void
    {
        SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest = false;

        $this->get('/iletisim')
            ->assertOk()
            ->assertDontSee('livewire', escape: false)
            ->assertDontSee('menu-base', escape: false);
    }

    public function test_nav_footer_and_home_link_to_the_contact_page(): void
    {
        $trLink = 'href="'.url('/iletisim').'"';
        $enLink = 'href="'.url('/en/contact').'"';

        // Desktop nav, mobile nav, footer and the home CTA ("talk to sales").
        $this->assertSame(4, substr_count($this->get('/')->getContent(), $trLink));
        $this->assertSame(4, substr_count($this->get('/en')->getContent(), $enLink));
        $this->get('/')->assertSee(__('marketing.cta.secondary', [], 'tr'));

        // With home FAQs, their lead links here too.
        Faq::factory()->create(['placement' => FaqPlacement::Home])
            ->translations()->create(['locale' => 'tr', 'question' => 'Soru?', 'answer' => 'Yanıt.']);
        $this->assertSame(5, substr_count($this->get('/')->getContent(), $trLink));

        $this->get('/iletisim')->assertSee('aria-current="page"', escape: false);
    }

    public function test_contact_details_come_from_the_site_settings(): void
    {
        $this->settings([
            'contact_email' => 'destek@restano.test',
            'phone' => '+90 212 555 01 23',
            'phone_has_whatsapp' => true,
            'instagram_url' => 'https://instagram.com/restano',
        ], [
            'tr' => ['address' => 'Moda Cd. 1, Kadıköy', 'support_hours' => 'Pzt–Cum, 09:00–18:00'],
            'en' => ['address' => null, 'support_hours' => 'Mon–Fri, 09:00–18:00'],
        ]);

        $this->get('/iletisim')
            ->assertOk()
            ->assertSee('href="mailto:destek@restano.test"', escape: false)
            ->assertSee('href="tel:+902125550123"', escape: false)
            ->assertSee('href="https://wa.me/902125550123"', escape: false)
            ->assertSee(__('marketing.contact_page.channels.phone_whatsapp', [], 'tr'))
            ->assertSee('Pzt–Cum, 09:00–18:00')
            ->assertSee('Moda Cd. 1, Kadıköy')
            ->assertSee('href="https://instagram.com/restano"', escape: false);

        // English hours; the missing English address falls back to the default locale.
        $this->get('/en/contact')
            ->assertSee('Mon–Fri, 09:00–18:00')
            ->assertDontSee('Pzt–Cum, 09:00–18:00')
            ->assertSee('Moda Cd. 1, Kadıköy');
    }

    public function test_footer_shows_contact_details_and_social_links_on_every_marketing_page(): void
    {
        $this->settings(['contact_email' => 'destek@restano.test', 'x_url' => 'https://x.com/restano']);

        foreach (['/', '/ozellikler', '/fiyatlar', '/en'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="mailto:destek@restano.test"', escape: false)
                ->assertSee('href="https://x.com/restano"', escape: false);
        }
    }

    public function test_empty_settings_render_no_channels_and_no_empty_links(): void
    {
        $this->get('/iletisim')
            ->assertOk()
            ->assertDontSee(__('marketing.contact_page.channels_title', [], 'tr'))
            ->assertDontSee('mailto:', escape: false)
            ->assertDontSee('tel:', escape: false)
            ->assertDontSee('wa.me', escape: false)
            ->assertDontSee(__('marketing.footer.social_label', [], 'tr'));

        $this->assertNull(SiteSetting::existing(), 'Public pages never create the settings row.');
    }

    public function test_whatsapp_link_appears_only_when_enabled(): void
    {
        $this->settings(['phone' => '+90 212 555 01 23', 'phone_has_whatsapp' => false]);

        $this->get('/iletisim')
            ->assertSee('href="tel:+902125550123"', escape: false)
            ->assertSee(__('marketing.contact_page.channels.phone', [], 'tr'))
            ->assertDontSee('wa.me', escape: false);
    }

    public function test_quick_answers_link_the_home_faq_only_when_it_exists(): void
    {
        $faqLink = 'href="'.url('/').'#faq"';

        $this->get('/iletisim')
            ->assertSee('href="'.url('/fiyatlar').'"', escape: false)
            ->assertSee('href="'.route('password.request').'"', escape: false)
            ->assertDontSee(__('marketing.contact_page.quick.faq', [], 'tr'));

        Faq::factory()->create(['placement' => FaqPlacement::Home])
            ->translations()->create(['locale' => 'tr', 'question' => 'Soru?', 'answer' => 'Yanıt.']);

        $this->get('/iletisim')->assertSee(__('marketing.contact_page.quick.faq', [], 'tr'));
    }

    public function test_a_valid_message_is_queued_to_the_site_settings_email(): void
    {
        Mail::fake();
        $this->settings(['contact_email' => 'destek@restano.test']);

        $this->post('/en/contact', $this->payload(['name' => '  Ayşe Demir ']))
            ->assertRedirect(url('/en/contact').'#contact-form')
            ->assertSessionHas('contact_sent', true);

        Mail::assertNothingSent();
        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
            return $mail->hasTo('destek@restano.test')
                && $mail->hasReplyTo('ayse@example.com', 'Ayşe Demir')
                && $mail->locale === config('locales.default')
                && $mail->contact->subject === ContactSubject::Setup
                && $mail->contact->locale === 'en';
        });
        Mail::assertQueuedCount(1);
    }

    public function test_without_a_contact_email_the_message_goes_to_the_mail_sender_address(): void
    {
        Mail::fake();
        config(['mail.from.address' => 'hello@restano.test']);

        $this->post('/iletisim', $this->payload())->assertSessionHas('contact_sent');

        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail): bool => $mail->hasTo('hello@restano.test'));
    }

    public function test_the_success_state_is_shown_after_sending(): void
    {
        Mail::fake();

        $this->followingRedirects()
            ->post('/iletisim', $this->payload())
            ->assertOk()
            ->assertSee(__('marketing.contact_page.sent_title', [], 'tr'))
            ->assertSee('role="status"', escape: false)
            ->assertDontSee('name="message"', escape: false);

        // The flash is one-shot: reloading shows the form again.
        $this->get('/iletisim')->assertSee('name="message"', escape: false);
    }

    public function test_invalid_input_is_rejected_with_translated_errors_and_nothing_is_sent(): void
    {
        Mail::fake();

        $this->from('/iletisim')
            ->post('/iletisim', [
                'name' => '',
                'email' => 'not-an-email',
                'subject' => 'free-text',
                'message' => 'short',
            ])
            ->assertRedirect('/iletisim')
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->from('/iletisim')
            ->post('/iletisim', $this->payload(['name' => "Ayşe\r\nBcc: x@evil.test", 'message' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors(['name', 'message']);

        Mail::assertNothingQueued();

        $this->followingRedirects()->from('/iletisim')->post('/iletisim', $this->payload(['email' => 'x']))
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee(__('marketing.contact_page.fields.email', [], 'tr'))
            ->assertSee('value="Ayşe Demir"', escape: false);
    }

    public function test_the_honeypot_answers_like_a_success_but_sends_nothing(): void
    {
        Mail::fake();

        $this->post('/iletisim', $this->payload([ContactRequest::HONEYPOT => 'https://spam.test']))
            ->assertRedirect(url('/iletisim').'#contact-form')
            ->assertSessionHas('contact_sent', true);

        Mail::assertNothingOutgoing();
    }

    public function test_submissions_are_rate_limited_per_ip(): void
    {
        Mail::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/iletisim', $this->payload())->assertSessionHas('contact_sent');
        }

        $this->post('/iletisim', $this->payload())
            ->assertRedirect(url('/iletisim').'#contact-form')
            ->assertSessionHasErrors(['message' => __('marketing.contact_page.throttled', [], 'tr')])
            ->assertSessionMissing('contact_sent');

        Mail::assertQueuedCount(3);

        // Another visitor is not affected.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post('/iletisim', $this->payload())
            ->assertSessionHas('contact_sent');
    }

    public function test_the_mail_is_queued_and_renders_visitor_input_escaped(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new ContactMessageMail(new ContactMessage('a', 'a@b.test', ContactSubject::Issue, 'x', 'tr')));

        $mail = new ContactMessageMail(new ContactMessage(
            name: 'Ayşe <b>Demir</b>',
            email: 'ayse@example.com',
            subject: ContactSubject::Issue,
            body: "<script>alert(1)</script>\n[tıkla](https://evil.test)",
            locale: 'en',
        ));
        $mail->locale('tr');

        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
        $mail->assertDontSeeInHtml('<script>', escape: false);
        $mail->assertDontSeeInHtml('href="https://evil.test"', escape: false);
        $mail->assertSeeInText('[tıkla](https://evil.test)');
        $mail->assertHasSubject(__('marketing.contact_mail.subject', ['subject' => ContactSubject::Issue->label('tr'), 'name' => 'Ayşe <b>Demir</b>'], 'tr'));
        $mail->assertSeeInHtml('English');
    }

    public function test_contact_copy_has_the_same_keys_in_every_locale(): void
    {
        $locales = config('locales.interface');

        foreach (['marketing.contact_page', 'marketing.contact_mail', 'panels.admin.site_settings'] as $key) {
            $reference = array_keys(Arr::dot(__($key, [], $locales[0])));

            foreach ($locales as $locale) {
                $this->assertSame($reference, array_keys(Arr::dot(__($key, [], $locale))), "{$key} keys differ in {$locale}.");
            }
        }

        foreach (ContactSubject::cases() as $subject) {
            foreach ($locales as $locale) {
                $this->assertNotSame("marketing.contact_page.subjects.{$subject->value}", $subject->label($locale));
            }
        }
    }
}
