<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Privacy notice and terms of use (generic drafts, legal review pending).
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_and_terms_render_at_localised_paths(): void
    {
        foreach (['/gizlilik' => ['privacy', 'tr'], '/en/privacy' => ['privacy', 'en'], '/kullanim-kosullari' => ['terms', 'tr'], '/en/terms' => ['terms', 'en']] as $path => [$document, $locale]) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<html lang="'.$locale.'">', escape: false)
                ->assertSee('<title>'.e(__("legal.{$document}.title", [], $locale)), escape: false)
                ->assertSee(__("legal.{$document}.sections.0.title", [], $locale))
                ->assertSee('<meta name="description" content="'.e(__("legal.{$document}.description", [], $locale)).'">', escape: false);
        }

        $this->get('/privacy')->assertNotFound();
        $this->get('/en/gizlilik')->assertNotFound();
    }

    public function test_canonical_and_hreflang(): void
    {
        $tr = url('/kullanim-kosullari');
        $en = url('/en/terms');

        foreach (['/kullanim-kosullari' => $tr, '/en/terms' => $en] as $path => $canonical) {
            $this->get($path)
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="tr" href="'.$tr.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="en" href="'.$en.'">', escape: false)
                ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$tr.'">', escape: false);
        }
    }

    public function test_the_draft_notice_is_shown_until_the_review_is_done(): void
    {
        $this->get('/gizlilik')->assertSee(__('legal.draft_title', [], 'tr'));

        config(['marketing.legal_review_pending' => false]);

        $this->get('/gizlilik')->assertOk()->assertDontSee(__('legal.draft_title', [], 'tr'));
    }

    public function test_footer_links_to_both_documents_in_the_current_locale(): void
    {
        $this->get('/')
            ->assertSee('href="'.url('/gizlilik').'"', escape: false)
            ->assertSee('href="'.url('/kullanim-kosullari').'"', escape: false);

        $this->get('/en')
            ->assertSee('href="'.url('/en/privacy').'"', escape: false)
            ->assertSee('href="'.url('/en/terms').'"', escape: false);
    }

    public function test_legal_texts_have_the_same_keys_in_every_locale(): void
    {
        $keys = fn (string $locale): array => array_keys(Arr::dot(require lang_path("{$locale}/legal.php")));

        $this->assertSame($keys('tr'), $keys('en'));
    }

    public function test_pages_are_central_domain_only(): void
    {
        $this->get('http://yaman.'.config('tenancy.central_domain').'/gizlilik')->assertNotFound();
    }
}
