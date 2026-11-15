<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Central-only surfaces (marketing, auth, panels, Livewire) must never be
 * served on a tenant subdomain.
 */
class DomainIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const CENTRAL = 'http://localhost';

    private const TENANT = 'http://acme.localhost';

    public static function centralOnlyGetRoutes(): array
    {
        return [
            'marketing home' => ['/'],
            'register' => ['/register'],
            'forgot password' => ['/forgot-password'],
            'reset password' => ['/reset-password/some-token'],
            'login redirect' => ['/login'],
            'email verification notice' => ['/email/verify'],
            'admin login' => ['/admin/login'],
            'admin dashboard' => ['/admin'],
            'business login' => ['/app/login'],
            'business panel' => ['/app'],
            'account' => ['/account'],
        ];
    }

    #[DataProvider('centralOnlyGetRoutes')]
    public function test_route_is_not_served_on_a_tenant_subdomain(string $path): void
    {
        $this->get(self::TENANT.$path)->assertNotFound();
    }

    #[DataProvider('centralOnlyGetRoutes')]
    public function test_route_is_served_on_the_central_domain(string $path): void
    {
        $status = $this->get(self::CENTRAL.$path)->getStatusCode();

        $this->assertContains($status, [200, 302], "{$path} returned {$status} on the central domain.");
    }

    public function test_tenant_subdomain_root_is_404_even_when_signed_in(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(self::TENANT.'/')
            ->assertNotFound();
    }

    public function test_central_only_post_endpoints_are_404_on_a_tenant_subdomain(): void
    {
        $this->post(self::TENANT.'/register')->assertNotFound();
        $this->post(self::TENANT.'/forgot-password')->assertNotFound();
        $this->post(self::TENANT.'/reset-password')->assertNotFound();
        $this->post(self::TENANT.'/logout')->assertNotFound();
        $this->postJson(self::TENANT.route('panels.livewire.update', absolute: false), [], ['X-Livewire' => '1'])->assertNotFound();
    }

    public function test_unknown_hosts_get_nothing_either(): void
    {
        $this->get('http://evil.example/')->assertNotFound();
        $this->get('http://evil.example/admin/login')->assertNotFound();
        $this->get('http://evil.example'.EndpointResolver::prefix().'/preview-file/x.png')->assertNotFound();
    }
}
