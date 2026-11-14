<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_panel_providers_are_registered(): void
    {
        $this->assertArrayHasKey(AdminPanelProvider::class, $this->app->getLoadedProviders());
        $this->assertArrayHasKey(AppPanelProvider::class, $this->app->getLoadedProviders());

        $admin = Filament::getPanel('admin');
        $app = Filament::getPanel('app');

        $this->assertSame('admin', $admin->getPath());
        $this->assertSame('app', $app->getPath());
        $this->assertSame([config('tenancy.central_domain')], $admin->getDomains());
        $this->assertSame([config('tenancy.central_domain')], $app->getDomains());
        $this->assertFalse($admin->hasRegistration());
        $this->assertFalse($app->hasRegistration());
        $this->assertFalse($admin->hasPasswordReset());
        $this->assertFalse($app->hasPasswordReset());
    }

    public static function panels(): array
    {
        return [
            'admin' => ['admin'],
            'app' => ['app'],
        ];
    }

    #[DataProvider('panels')]
    public function test_login_page_loads(string $panel): void
    {
        $this->get("/{$panel}/login")->assertOk();
    }

    #[DataProvider('panels')]
    public function test_guest_is_redirected_to_the_panel_login(string $panel): void
    {
        $this->get("/{$panel}")->assertRedirect("http://localhost/{$panel}/login");
    }

    #[DataProvider('panels')]
    public function test_user_without_any_grant_is_forbidden(string $panel): void
    {
        $this->actingAs(User::factory()->create())
            ->get("/{$panel}")
            ->assertForbidden();
    }

    #[DataProvider('panels')]
    public function test_panels_are_not_served_on_tenant_subdomains(string $panel): void
    {
        $this->get("http://acme.localhost/{$panel}/login")->assertNotFound();
    }

    public function test_public_site_still_works_and_loads_no_livewire_or_filament_assets(): void
    {
        // Livewire tracks rendered components in static state that survives
        // between tests in one process; reset it so only this request counts.
        Livewire::flushState();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('livewire', $html);
        $this->assertStringNotContainsStringIgnoringCase('filament', $html);
    }

    public function test_livewire_components_exist_only_inside_filament(): void
    {
        $this->assertDirectoryDoesNotExist(app_path('Livewire'));
        $this->assertDirectoryDoesNotExist(resource_path('views/livewire'));
    }
}
