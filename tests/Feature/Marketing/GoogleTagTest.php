<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\Plan;
use App\Models\User;
use App\Support\GoogleTag;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Google Ads tag: only on marketing, /register and /email/verify, CSP widened
 * only for those responses, never on menus, /account, panels or token pages;
 * the sign-up conversion is reported once after registration.
 */
class GoogleTagTest extends TestCase
{
    use RefreshDatabase;

    private const string ID = 'AW-18396163067';

    private const string CONVERSION = 'AW-18396163067/AbCdEf_123';

    private function configure(?string $id = self::ID, ?string $conversion = self::CONVERSION): void
    {
        config(['services.google_ads.id' => $id, 'services.google_ads.signup_conversion' => $conversion]);
    }

    private function scriptSources(TestResponse $response): string
    {
        preg_match('/script-src ([^;]*)/', (string) $response->headers->get('Content-Security-Policy'), $match);

        return $match[1] ?? '';
    }

    private function assertTag(TestResponse $response, string $label): void
    {
        $response->assertSee('data-google-tag="'.self::ID.'"', false)->assertSee('data-consent-banner', false);
        $this->assertStringContainsString('https://www.googletagmanager.com', $this->scriptSources($response), $label);
        $this->assertStringNotContainsString("'unsafe-inline'", $this->scriptSources($response), $label);
        $this->assertStringContainsString('frame-src', (string) $response->headers->get('Content-Security-Policy'), $label);
        foreach (['img-src', 'connect-src'] as $directive) {
            preg_match("/{$directive} ([^;]*)/", (string) $response->headers->get('Content-Security-Policy'), $match);
            $this->assertStringContainsString('https://ad.doubleclick.net', $match[1] ?? '', "{$label}: {$directive}");
        }
        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\ssrc=")/i', (string) $response->getContent(), "{$label}: inline script.");
        $response->assertDontSee('googletagmanager.com/gtm.js', false); // loaded by app.js after consent only
    }

    private function assertNoTag(TestResponse $response, string $label): void
    {
        $response->assertDontSee('data-google-tag', false)->assertDontSee('data-consent-banner', false);
        $this->assertStringNotContainsString('google', (string) $response->headers->get('Content-Security-Policy'), $label);
    }

    public function test_nothing_is_rendered_or_allowed_while_unconfigured(): void
    {
        $this->configure(null, null);

        foreach (['/', '/fiyatlar', '/en/pricing', '/register'] as $path) {
            $this->assertNoTag($this->get($path)->assertOk(), $path);
        }
    }

    public function test_a_malformed_id_disables_the_tag(): void
    {
        $this->configure('AW-1"><script>', null);

        $this->assertNoTag($this->get('/')->assertOk(), '/');
        $this->assertNull(app(GoogleTag::class)->signupConversion());
    }

    public function test_marketing_and_registration_pages_render_the_tag(): void
    {
        $this->configure();

        foreach (['/', '/ozellikler', '/fiyatlar', '/temalar', '/qr-stand', '/iletisim', '/gizlilik', '/en', '/en/pricing', '/register'] as $path) {
            $this->assertTag($this->get($path)->assertOk(), $path);
        }
    }

    public function test_menus_account_panels_and_token_pages_never_get_the_tag(): void
    {
        $this->configure();
        $this->seed(DemoSeeder::class);

        $this->get('/')->assertOk(); // a tag page first: the flag must not leak into later responses

        foreach (['/m/kahve-konagi/moda/menu', '/m/kahve-konagi/moda', '/forgot-password', '/reset-password/some-token?email=a@example.com', '/invitations/'.str_repeat('a', 43), '/app/login', '/admin/login'] as $path) {
            $this->assertNoTag($this->get($path), $path);
        }

        $owner = User::query()->where('email', 'sahip@kahvekonagi.test')->sole();
        $this->assertNoTag($this->actingAs($owner)->get('/account')->assertOk(), '/account');
    }

    public function test_the_signup_conversion_is_reported_once_on_the_verification_page(): void
    {
        $this->configure();
        Notification::fake();
        Plan::factory()->create(['code' => config('subscriptions.default_plan')]);

        $this->post('/register', [
            'name' => 'Ayşe Yaman', 'email' => 'ayse@example.com',
            'password' => 'a-Strong-passw0rd', 'password_confirmation' => 'a-Strong-passw0rd',
            'business_name' => 'Yaman Kahvesi', 'slug' => 'yaman-kahvesi', 'terms' => '1',
        ])->assertRedirect();

        // The panel redirect in between doesn't consume it.
        $this->get('/app/yaman-kahvesi')->assertRedirect(route('verification.notice'));

        $first = $this->get(route('verification.notice'))->assertOk();
        $this->assertTag($first, '/email/verify');
        $first->assertSee('data-signup-conversion="'.self::CONVERSION.'"', false);

        $this->get(route('verification.notice'))->assertOk()->assertDontSee('data-signup-conversion', false);
    }

    public function test_no_conversion_flag_without_a_conversion_label(): void
    {
        $this->configure(self::ID, null);
        Notification::fake();
        Plan::factory()->create(['code' => config('subscriptions.default_plan')]);

        $this->post('/register', [
            'name' => 'Ayşe Yaman', 'email' => 'ayse@example.com',
            'password' => 'a-Strong-passw0rd', 'password_confirmation' => 'a-Strong-passw0rd',
            'business_name' => 'Yaman Kahvesi', 'slug' => 'yaman-kahvesi', 'terms' => '1',
        ])->assertRedirect();

        $this->get(route('verification.notice'))->assertOk()->assertDontSee('data-signup-conversion', false);
    }
}
