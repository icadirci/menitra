<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * The session (and XSRF) cookie must be host-only on the central domain, so a
 * browser never sends it to {slug}.central_domain (docs/architecture/tenancy.md).
 *
 * Besides the config value, these tests read the raw Set-Cookie headers of real
 * responses and apply the browser's RFC 6265 domain-matching rule.
 */
class SessionCookieTest extends TestCase
{
    use RefreshDatabase;

    private const CENTRAL_HOST = 'localhost';

    private const TENANT_HOST = 'acme.localhost';

    public function test_session_domain_is_not_configured(): void
    {
        $this->assertNull(config('session.domain'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public static function centralPages(): array
    {
        return [
            'register' => ['/register'],
            'forgot password' => ['/forgot-password'],
            'admin login (Filament)' => ['/admin/login'],
            'business login (Filament)' => ['/app/login'],
        ];
    }

    #[DataProvider('centralPages')]
    public function test_raw_set_cookie_headers_carry_no_domain_attribute(string $path): void
    {
        $response = $this->get('http://'.self::CENTRAL_HOST.$path)->assertOk();

        $raw = $response->headers->all('set-cookie');
        $sessionCookie = config('session.cookie');

        $this->assertNotEmpty(
            array_filter($raw, fn (string $header) => str_starts_with($header, $sessionCookie.'=')),
            "No {$sessionCookie} cookie was set by {$path}.",
        );

        foreach ($raw as $header) {
            $this->assertDoesNotMatchRegularExpression('/;\s*domain=/i', $header, "Cookie has a Domain attribute: {$header}");
        }
    }

    #[DataProvider('centralPages')]
    public function test_browser_would_not_send_the_cookies_to_a_tenant_subdomain(string $path): void
    {
        $cookies = $this->cookiesFrom($this->get('http://'.self::CENTRAL_HOST.$path)->assertOk());

        foreach ($cookies as $cookie) {
            $this->assertTrue(
                $this->browserWouldSend($cookie, setBy: self::CENTRAL_HOST, to: self::CENTRAL_HOST),
                "{$cookie->getName()} must still reach the central host.",
            );
            $this->assertFalse(
                $this->browserWouldSend($cookie, setBy: self::CENTRAL_HOST, to: self::TENANT_HOST),
                "{$cookie->getName()} would leak to ".self::TENANT_HOST.'.',
            );
        }

        $session = $cookies[config('session.cookie')];
        $this->assertTrue($session->isHttpOnly());
        $this->assertSame('lax', $session->getSameSite());
    }

    public function test_the_check_detects_a_parent_domain_misconfiguration(): void
    {
        // Regression guard for the test itself: a parent-domain cookie leaks.
        config(['session.domain' => '.'.self::CENTRAL_HOST]);

        $session = $this->cookiesFrom($this->get('http://'.self::CENTRAL_HOST.'/register'))[config('session.cookie')];

        $this->assertTrue($this->browserWouldSend($session, setBy: self::CENTRAL_HOST, to: self::TENANT_HOST));
    }

    /**
     * @return array<string, Cookie>
     */
    private function cookiesFrom(TestResponse $response): array
    {
        $cookies = [];

        foreach ($response->headers->all('set-cookie') as $header) {
            $cookie = Cookie::fromString($header);
            $cookies[$cookie->getName()] = $cookie;
        }

        return $cookies;
    }

    /**
     * RFC 6265 §5.1.3 / §5.3: without a Domain attribute a cookie is host-only;
     * with one, it is sent to that domain and all of its subdomains.
     */
    private function browserWouldSend(Cookie $cookie, string $setBy, string $to): bool
    {
        $domain = $cookie->getDomain();

        if ($domain === null || $domain === '') {
            return $to === $setBy;
        }

        $domain = ltrim(strtolower($domain), '.');

        return $to === $domain || str_ends_with($to, '.'.$domain);
    }
}
