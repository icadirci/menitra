<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Reverse proxy (TLS on the host's nginx): forwarded client IP, scheme and
 * port are trusted only from TRUSTED_PROXIES, and X-Forwarded-Host never.
 */
class TrustedProxyTest extends TestCase
{
    private const PROXY_IP = '172.18.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__proxy-probe', fn (Request $request) => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
            'url' => url('/x'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function forwardedHeaders(): array
    {
        return [
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Host' => 'evil.example',
        ];
    }

    public function test_forwarded_ip_and_scheme_are_used_from_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '172.16.0.0/12']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => self::PROXY_IP])
            ->withHeaders($this->forwardedHeaders())
            ->get('http://localhost/__proxy-probe');

        $response->assertOk()->assertJson([
            'ip' => '203.0.113.7',
            'secure' => true,
            'url' => 'https://localhost/x',
        ]);
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_forwarded_host_is_never_trusted(): void
    {
        config(['trustedproxy.proxies' => '172.16.0.0/12']);

        $this->withServerVariables(['REMOTE_ADDR' => self::PROXY_IP])
            ->withHeaders($this->forwardedHeaders())
            ->get('http://localhost/__proxy-probe')
            ->assertJson(['host' => 'localhost']);
    }

    public function test_forwarded_headers_are_ignored_from_untrusted_addresses(): void
    {
        config(['trustedproxy.proxies' => '172.16.0.0/12']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeaders($this->forwardedHeaders())
            ->get('http://localhost/__proxy-probe');

        $response->assertJson([
            'ip' => '198.51.100.20',
            'secure' => false,
            'host' => 'localhost',
            'url' => 'http://localhost/x',
        ]);
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_no_proxy_is_trusted_without_configuration(): void
    {
        $this->assertNull(config('trustedproxy.proxies'));

        $this->withServerVariables(['REMOTE_ADDR' => self::PROXY_IP])
            ->withHeaders($this->forwardedHeaders())
            ->get('http://localhost/__proxy-probe')
            ->assertJson(['ip' => self::PROXY_IP, 'secure' => false]);
    }
}
