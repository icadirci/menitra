<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Exceptions\MenuUnavailableException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * HTTP error pages (resources/views/errors): marketing design on the central
 * domain, a plain platform page without session on menu hosts.
 */
class ErrorPagesTest extends TestCase
{
    private const CENTRAL = 'http://localhost';

    private const TENANT = 'http://acme.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        // Production-like rendering: no debug page for 500.
        config(['app.debug' => false]);

        $throw = function (string $code): never {
            match ((int) $code) {
                419 => throw new TokenMismatchException,
                429 => throw new ThrottleRequestsException,
                500 => throw new RuntimeException('boom'),
                default => abort((int) $code),
            };
        };

        // Central page with the session (`web` group), like marketing and auth.
        Route::domain('localhost')->middleware('web')->get('/_error/{code}', $throw);
        // Menu hosts: no `web` group, exactly like routes/menu.php.
        Route::domain('localhost')->get('/m/{tenant}/_error/{code}', fn (string $tenant, string $code) => $throw($code));
        Route::domain('{tenant}.localhost')->get('/_error/{code}', fn (string $tenant, string $code) => $throw($code));
        Route::domain('{tenant}.localhost')->get('/_unavailable', fn () => throw new MenuUnavailableException('Acme'));
    }

    public static function statusCodes(): array
    {
        return ['403' => [403], '404' => [404], '419' => [419], '429' => [429], '500' => [500], '503' => [503]];
    }

    #[DataProvider('statusCodes')]
    public function test_central_domain_renders_the_marketing_error_page(int $code): void
    {
        $this->get(self::CENTRAL.'/_error/'.$code)
            ->assertStatus($code)
            ->assertSee('data-error-page="central"', escape: false)
            ->assertSee(__("errors.{$code}.title"))
            ->assertSee(__("errors.{$code}.body"))
            ->assertSee('<meta name="robots" content="noindex">', escape: false);
    }

    #[DataProvider('statusCodes')]
    public function test_tenant_subdomain_renders_the_platform_error_page_without_session(int $code): void
    {
        $response = $this->get(self::TENANT.'/_error/'.$code)
            ->assertStatus($code)
            ->assertSee('data-error-page="menu"', escape: false)
            ->assertSee(__("errors.{$code}.title"))
            ->assertDontSee('app.js')
            ->assertDontSee('livewire', false);

        $this->assertNoSessionCookie($response->headers->all('set-cookie'));
    }

    #[DataProvider('statusCodes')]
    public function test_menu_path_renders_the_platform_error_page_without_session(int $code): void
    {
        $response = $this->get(self::CENTRAL.'/m/acme/_error/'.$code)
            ->assertStatus($code)
            ->assertSee('data-error-page="menu"', escape: false)
            ->assertSee(__("errors.{$code}.title"));

        $this->assertNoSessionCookie($response->headers->all('set-cookie'));
    }

    public function test_unknown_urls_use_the_right_variant(): void
    {
        $this->get(self::CENTRAL.'/does-not-exist')
            ->assertNotFound()
            ->assertSee('data-error-page="central"', escape: false);

        $tenant = $this->get(self::TENANT.'/a/b/c')
            ->assertNotFound()
            ->assertSee('data-error-page="menu"', escape: false);
        $this->assertNoSessionCookie($tenant->headers->all('set-cookie'));

        $this->get(self::CENTRAL.'/q/unknown')
            ->assertNotFound()
            ->assertSee('data-error-page="menu"', escape: false);
    }

    public function test_english_marketing_urls_get_the_english_page(): void
    {
        // Default locale first: the test app instance keeps the locale between requests.
        $this->get(self::CENTRAL.'/does-not-exist')
            ->assertSee(trans('errors.404.title', [], 'tr'));

        $this->get(self::CENTRAL.'/en/does-not-exist')
            ->assertNotFound()
            ->assertSee(trans('errors.404.title', [], 'en'))
            ->assertSee('<html lang="en">', escape: false);
    }

    public function test_other_status_codes_fall_back_to_the_generic_pages(): void
    {
        Route::domain('localhost')->get('/_error-generic/{code}', fn (string $code) => abort((int) $code));

        $this->get(self::CENTRAL.'/_error-generic/410')
            ->assertStatus(410)
            ->assertSee(__('errors.client.title'));

        $this->get(self::CENTRAL.'/_error-generic/502')
            ->assertStatus(502)
            ->assertSee(__('errors.server.title'));
    }

    public function test_explicit_403_reason_is_shown_but_the_default_authorization_text_is_not(): void
    {
        Route::domain('localhost')->get('/_forbidden/reason', fn () => abort(403, 'Özel neden'));
        Route::domain('localhost')->get('/_forbidden/policy', fn () => throw new AuthorizationException);

        $this->get(self::CENTRAL.'/_forbidden/reason')
            ->assertForbidden()
            ->assertSee('Özel neden')
            ->assertDontSee(__('errors.403.body'));

        $this->get(self::CENTRAL.'/_forbidden/policy')
            ->assertForbidden()
            ->assertSee(__('errors.403.body'))
            ->assertDontSee('This action is unauthorized.');
    }

    public function test_menu_unavailable_page_is_unchanged(): void
    {
        $response = $this->get(self::TENANT.'/_unavailable')
            ->assertStatus(503)
            ->assertSee(__('menu.unavailable.title'))
            ->assertSee(__('menu.unavailable.business', ['name' => 'Acme']))
            ->assertDontSee('data-error-page', false)
            ->assertHeader('X-Robots-Tag', 'noindex');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * @param  list<string>  $setCookies
     */
    private function assertNoSessionCookie(array $setCookies): void
    {
        foreach ($setCookies as $header) {
            $this->assertStringStartsNotWith(config('session.cookie').'=', $header);
            $this->assertStringStartsNotWith('XSRF-TOKEN=', $header);
        }
    }
}
