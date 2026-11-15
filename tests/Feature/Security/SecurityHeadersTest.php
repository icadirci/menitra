<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\MarketingUrl;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Phase 8: security headers and CSP (SecurityHeaders), public vs panel
 * profile, and proof that the strict public profile can't break public pages
 * (they contain no inline scripts or event handlers).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, list<string>>
     */
    private function csp(TestResponse $response): array
    {
        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertNotSame('', $policy, 'Content-Security-Policy is missing.');
        $directives = [];

        foreach (array_filter(array_map('trim', explode(';', $policy))) as $directive) {
            $parts = preg_split('/\s+/', $directive);
            $directives[array_shift($parts)] = $parts;
        }

        return $directives;
    }

    private function assertBaseHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));

        $csp = $this->csp($response);
        $this->assertSame(["'none'"], $csp['frame-ancestors']);
        $this->assertSame(["'none'"], $csp['object-src']);
        $this->assertSame(["'self'"], $csp['base-uri']);
        $this->assertSame(["'self'"], $csp['form-action']);
        $this->assertSame(["'self'"], $csp['default-src']);
    }

    private function assertPublicProfile(TestResponse $response): void
    {
        $this->assertBaseHeaders($response);
        $csp = $this->csp($response);

        $this->assertNotContains("'unsafe-inline'", $csp['script-src'], 'Public pages must not allow inline scripts.');
        $this->assertSame(["'self'", "'unsafe-eval'"], $csp['script-src']);
    }

    /**
     * The strict profile works only if the page has no inline script, no
     * inline event handler and no third-party script.
     */
    private function assertNoInlineScripts(TestResponse $response, string $label): void
    {
        $html = (string) $response->getContent();

        preg_match_all('/<script\b([^>]*)>/i', $html, $tags);

        foreach ($tags[1] as $attributes) {
            $this->assertMatchesRegularExpression('/\ssrc="/', $attributes, "{$label}: inline <script> found.");
            $this->assertMatchesRegularExpression('#src="(http://localhost|/)#', $attributes, "{$label}: third-party script found.");
        }

        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $html, "{$label}: inline event handler found.");
    }

    public function test_marketing_and_auth_pages_get_the_public_profile_and_have_no_inline_scripts(): void
    {
        $paths = ['/register', '/forgot-password'];

        foreach (MarketingUrl::PAGES as $page) {
            foreach (MarketingUrl::alternates($page) as $url) {
                $paths[] = $url;
            }
        }

        foreach ($paths as $path) {
            $response = $this->get($path)->assertOk();
            $this->assertPublicProfile($response);
            $this->assertNoInlineScripts($response, $path);
        }
    }

    public function test_public_menus_get_the_public_profile_with_the_media_origin_and_keep_http_caching(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['/m/kahve-konagi/moda/menu', '/m/kahve-konagi/moda', '/m/noon/merkez/menu', '/m/maison-ardoise/merkez/menu', '/m/kiln-coffee/merkez/menu', '/m/petal-bakehouse/merkez/menu'] as $path) {
            $response = $this->get($path)->assertOk();

            $this->assertPublicProfile($response);
            $this->assertNoInlineScripts($response, $path);
            $this->assertContains('http://localhost', $this->csp($response)['img-src'], 'Media disk origin (tenant images) must be allowed.');
            $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
            $this->assertNotNull($response->headers->get('ETag'));
            $response->assertHeaderMissing('Set-Cookie');
        }
    }

    public function test_error_and_unavailable_pages_carry_the_headers_too(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertPublicProfile($this->get('/m/does-not-exist/x')->assertNotFound());
        $this->assertPublicProfile($this->get('/m/eski-firin/merkez/menu')->assertStatus(503));
        $this->assertBaseHeaders($this->get('http://yaman.localhost/admin')->assertNotFound());
    }

    public function test_account_page_gets_the_public_profile(): void
    {
        $this->seed(DemoSeeder::class);
        $owner = User::query()->where('email', 'sahip@kahvekonagi.test')->sole();

        $response = $this->actingAs($owner)->get('/account')->assertOk();

        $this->assertPublicProfile($response);
        $this->assertNoInlineScripts($response, '/account');
    }

    public function test_panels_get_the_documented_inline_script_exception_and_still_work(): void
    {
        foreach (['/admin/login', '/app/login'] as $path) {
            $response = $this->get($path)->assertOk()->assertSee('livewire', escape: false);

            $this->assertBaseHeaders($response);
            $csp = $this->csp($response);
            $this->assertContains("'unsafe-inline'", $csp['script-src'], 'Filament/Livewire inline scripts need the panel exception.');
            $this->assertContains('blob:', $csp['img-src'], 'Upload previews use blob: URLs.');
            $this->assertContains('blob:', $csp['worker-src']);
            $this->assertNotContains('*', $csp['script-src']);
        }

        $this->seed(DemoSeeder::class);
        $owner = User::query()->where('email', 'sahip@kahvekonagi.test')->sole();
        $response = $this->actingAs($owner)->get('/app/kahve-konagi')->assertOk();
        $this->assertContains("'unsafe-inline'", $this->csp($response)['script-src']);
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_the_vite_dev_server_is_allowed_only_while_it_runs(): void
    {
        $this->withVite(); // the real Vite (the test default is a fake without a hot file)
        $hot = tempnam(sys_get_temp_dir(), 'hot');
        file_put_contents($hot, 'http://localhost:5173');
        Vite::useHotFile($hot);

        try {
            $csp = $this->csp($this->get('/'));
            $this->assertContains('http://localhost:5173', $csp['script-src']);
            $this->assertContains('ws://localhost:5173', $csp['connect-src']);
        } finally {
            unlink($hot);
        }

        $this->assertNotContains('http://localhost:5173', $this->csp($this->get('/'))['script-src']);
    }

    public function test_marketing_pages_never_enter_shared_caches(): void
    {
        foreach (['/', '/en/pricing', '/temalar', '/gizlilik'] as $path) {
            $cache = (string) $this->get($path)->headers->get('Cache-Control');
            $this->assertStringContainsString('private', $cache, $path);
            $this->assertStringContainsString('no-cache', $cache, $path);
            $this->assertStringNotContainsString('public', $cache, $path);
        }

        // Signed in, the nav differs ("My account"): still private.
        $this->seed(DemoSeeder::class);
        $owner = User::query()->where('email', 'sahip@kahvekonagi.test')->sole();
        $response = $this->actingAs($owner)->get('/')->assertSee(__('marketing.nav.account'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }
}
