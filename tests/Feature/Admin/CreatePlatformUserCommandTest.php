<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PlatformRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * platform:create-user: a platform user without a business.
 */
class CreatePlatformUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_super_admin_without_a_business(): void
    {
        $this->artisan('platform:create-user', ['email' => 'Admin@Menitra.com', '--name' => 'Yönetici'])
            ->expectsQuestion('Password', 'Uzun-Sifre-2026!')
            ->expectsQuestion('Password (again)', 'Uzun-Sifre-2026!')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@menitra.com')->sole();
        $this->assertSame(PlatformRole::SuperAdmin, $user->platform_role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Uzun-Sifre-2026!', $user->password));
        $this->assertSame(0, Tenant::query()->count());
        $this->assertSame(0, TenantMembership::query()->count());
    }

    public function test_a_generated_password_is_printed_once_and_staff_can_be_created(): void
    {
        $this->artisan('platform:create-user', ['email' => 'destek@menitra.com', '--name' => 'Destek', '--role' => 'platform_staff', '--generate-password'])
            ->expectsOutputToContain('Password (shown only once):')
            ->assertSuccessful();

        $this->assertSame(PlatformRole::PlatformStaff, User::query()->where('email', 'destek@menitra.com')->sole()->platform_role);
    }

    public function test_bad_input_creates_nothing(): void
    {
        User::factory()->create(['email' => 'var@menitra.com']);

        $this->artisan('platform:create-user', ['email' => 'var@menitra.com', '--name' => 'X'])->assertFailed();
        $this->artisan('platform:create-user', ['email' => 'yeni@menitra.com', '--name' => 'X', '--role' => 'owner'])->assertFailed();
        $this->artisan('platform:create-user', ['email' => 'yeni@menitra.com', '--name' => 'X'])
            ->expectsQuestion('Password', 'Uzun-Sifre-2026!')
            ->expectsQuestion('Password (again)', 'baska-sifre')
            ->assertFailed();

        $this->assertSame(1, User::query()->count());
    }
}
