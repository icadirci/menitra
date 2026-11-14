<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\PlatformRole;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin')
            ->assertOk();
    }

    public function test_platform_staff_can_open_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->platformStaff()->create())
            ->get('/admin')
            ->assertOk();
    }

    public function test_user_without_platform_role_cannot_open_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_platform_role_grants_nothing_in_the_business_panel(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/app')
            ->assertForbidden();
    }

    public function test_platform_role_is_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-password',
            'platform_role' => 'super_admin',
        ]);

        $this->assertNull($user->fresh()->platform_role);
    }

    public function test_platform_user_can_log_in_through_the_admin_login_page(): void
    {
        User::factory()->platformStaff()->create(['email' => 'staff@example.com']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'staff@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('http://localhost/admin');

        $this->assertAuthenticated();
    }

    public function test_user_without_platform_role_cannot_log_in_to_admin(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'owner@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->superAdmin()->create(['email' => 'root@example.com']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'root@example.com', 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_assign_role_command_grants_and_revokes(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com']);

        $this->artisan('platform:assign-role', ['email' => 'ops@example.com', 'role' => 'platform_staff'])
            ->assertSuccessful();
        $this->assertSame(PlatformRole::PlatformStaff, $user->fresh()->platform_role);

        $this->artisan('platform:assign-role', ['email' => 'ops@example.com', '--revoke' => true])
            ->assertSuccessful();
        $this->assertNull($user->fresh()->platform_role);
    }

    public function test_assign_role_command_rejects_unknown_roles_and_users(): void
    {
        User::factory()->create(['email' => 'ops@example.com']);

        $this->artisan('platform:assign-role', ['email' => 'ops@example.com', 'role' => 'owner'])
            ->assertFailed();
        $this->artisan('platform:assign-role', ['email' => 'nobody@example.com', 'role' => 'super_admin'])
            ->assertFailed();
    }
}
