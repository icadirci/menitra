<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 8 rate limit review: every public write endpoint and every public
 * read endpoint that costs queries on a miss is throttled.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route name => required throttle middleware.
     */
    private const array THROTTLED = [
        'register.store' => 'throttle:registration',
        'password.email' => 'throttle:password-reset',
        'password.update' => 'throttle:password-reset',
        'verification.verify' => 'throttle:6,1',
        'verification.send' => 'throttle:6,1',
        'marketing.tr.contact.store' => 'throttle:contact',
        'marketing.en.contact.store' => 'throttle:contact',
        'menu.qr' => 'throttle:qr',
        'menu.path.home' => 'throttle:menu',
        'menu.path.location' => 'throttle:menu',
        'menu.path.menu' => 'throttle:menu',
        'menu.subdomain.home' => 'throttle:menu',
        'menu.subdomain.location' => 'throttle:menu',
        'menu.subdomain.menu' => 'throttle:menu',
    ];

    public function test_public_endpoints_are_throttled(): void
    {
        foreach (self::THROTTLED as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} is missing.");
            $this->assertContains($middleware, $route->gatherMiddleware(), "{$name} must use {$middleware}.");
        }
    }

    public function test_every_unauthenticated_post_route_is_throttled(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! in_array('POST', $route->methods(), true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $name = (string) $route->getName();
            // Livewire: the default update route answers 404 (a custom, central-
            // domain one exists — LivewireEndpointTest); panel updates re-run
            // panel auth, and Filament's login throttles itself (5/min); uploads
            // are throttled through livewire.temporary_file_upload.middleware.
            $authenticated = in_array('auth', $middleware, true)
                || in_array($name, ['default-livewire.update', 'panels.livewire.update', 'livewire.upload-file'], true)
                || str_starts_with($name, 'filament.');
            $throttled = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));

            $this->assertTrue($authenticated || $throttled, "POST {$route->uri()} is neither authenticated nor throttled.");
        }
    }

    public function test_menu_pages_answer_429_over_the_limit_per_ip(): void
    {
        $this->seed(DemoSeeder::class);
        config(['tenancy.menu_rate_limit' => 3]);

        foreach (range(1, 3) as $i) {
            $this->get('/m/noon/merkez/menu')->assertOk();
        }

        $this->get('/m/noon/merkez/menu')->assertStatus(429);
        $this->get('/m/unknown-slug/x')->assertStatus(429);

        // Another IP has its own budget.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get('/m/noon/merkez/menu')->assertOk();
    }
}
