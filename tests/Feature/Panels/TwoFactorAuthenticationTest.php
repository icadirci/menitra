<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Filament\Shared\Auth\EditProfile;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Factories\UserFactory;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const SET_UP_URL = 'http://localhost/admin/multi-factor-authentication/set-up';

    private function currentCode(): string
    {
        return app(Google2FA::class)->getCurrentOtp(UserFactory::TWO_FACTOR_SECRET);
    }

    private function member(User $user, ?Tenant $tenant = null): User
    {
        TenantMembership::factory()
            ->for($tenant ?? Tenant::factory()->create())
            ->for($user)
            ->create();

        return $user;
    }

    // --- /admin: mandatory --------------------------------------------------

    public function test_platform_user_without_two_factor_is_sent_to_set_up(): void
    {
        $user = User::factory()->superAdmin()->withoutTwoFactor()->create();

        $this->actingAs($user)->get('/admin')->assertRedirect(self::SET_UP_URL);
        $this->actingAs($user)->get('/admin/plans')->assertRedirect(self::SET_UP_URL);
        $this->actingAs($user)->get('/admin/profile')->assertRedirect(self::SET_UP_URL);
    }

    public function test_platform_staff_without_two_factor_is_sent_to_set_up(): void
    {
        $this->actingAs(User::factory()->platformStaff()->withoutTwoFactor()->create())
            ->get('/admin')
            ->assertRedirect(self::SET_UP_URL);
    }

    public function test_set_up_page_renders_for_platform_user_without_two_factor(): void
    {
        $this->actingAs(User::factory()->superAdmin()->withoutTwoFactor()->create())
            ->get(self::SET_UP_URL)
            ->assertOk();
    }

    public function test_set_up_page_is_refused_without_platform_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(self::SET_UP_URL)
            ->assertForbidden();
    }

    public function test_platform_user_with_two_factor_reaches_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/profile')->assertOk();
    }

    // --- login challenge -------------------------------------------------------

    public function test_admin_login_requires_the_code_after_the_password(): void
    {
        User::factory()->superAdmin()->create(['email' => 'root@example.com']);
        Filament::setCurrentPanel('admin');

        $login = Livewire::test(Login::class)
            ->fillForm(['email' => 'root@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        $this->assertGuest();

        $login->fillForm(['multiFactor' => ['app' => ['code' => $this->currentCode()]]])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('http://localhost/admin');

        $this->assertAuthenticated();
    }

    public function test_wrong_code_is_rejected(): void
    {
        User::factory()->superAdmin()->create(['email' => 'root@example.com']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'root@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->fillForm(['multiFactor' => ['app' => ['code' => $this->currentCode() === '000000' ? '111111' : '000000']]])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest();
    }

    public function test_recovery_code_logs_in_once(): void
    {
        $user = User::factory()->superAdmin()->create(['email' => 'root@example.com']);
        Filament::setCurrentPanel('admin');

        $attempt = fn () => Livewire::test(Login::class)
            ->fillForm(['email' => 'root@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->fillForm(['multiFactor' => ['app' => ['useRecoveryCode' => true, 'recoveryCode' => 'recovery-code-3']]])
            ->call('authenticate');

        $attempt()->assertHasNoFormErrors()->assertRedirect('http://localhost/admin');
        $this->assertAuthenticated();
        $this->assertCount(7, $user->fresh()->getAppAuthenticationRecoveryCodes());

        auth()->logout();

        $attempt()->assertHasFormErrors();
        $this->assertGuest();
    }

    public function test_business_login_also_challenges_users_with_two_factor(): void
    {
        // The panels share the session: /app/login must not become a way into
        // /admin without the second factor.
        $this->member(User::factory()->superAdmin()->create(['email' => 'root@example.com']));
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'root@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_both_panels_use_the_same_providers(): void
    {
        $admin = array_keys(Filament::getPanel('admin')->getMultiFactorAuthenticationProviders());
        $app = array_keys(Filament::getPanel('app')->getMultiFactorAuthenticationProviders());

        $this->assertSame(['app'], $admin);
        $this->assertSame($admin, $app);
        $this->assertTrue(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired());
        $this->assertFalse(Filament::getPanel('app')->isMultiFactorAuthenticationRequired());
    }

    // --- /app: optional --------------------------------------------------------

    public function test_business_panel_works_without_two_factor(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $user = $this->member(User::factory()->create(), $tenant);

        $this->actingAs($user)->get('/app/yaman-kahvesi')->assertOk();
        $this->actingAs($user)->get('/app/profile')->assertOk();
    }

    public function test_business_login_without_two_factor_needs_no_code(): void
    {
        $this->member(User::factory()->create(['email' => 'owner@example.com']));
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'owner@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertAuthenticated();
    }

    // --- profile page and storage ---------------------------------------------

    public function test_profile_page_does_not_save_profile_fields(): void
    {
        $user = $this->member(User::factory()->create(['email' => 'owner@example.com']));
        Filament::setCurrentPanel('app');
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->set('data.email', 'attacker@example.com')
            ->set('data.name', 'Mallory')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('owner@example.com', $user->fresh()->email);
    }

    public function test_secret_and_recovery_codes_are_encrypted_and_hidden(): void
    {
        $user = User::factory()->superAdmin()->create();
        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotSame(UserFactory::TWO_FACTOR_SECRET, $row->app_authentication_secret);
        $this->assertStringNotContainsString('$2y$', $row->app_authentication_recovery_codes);
        $this->assertSame(UserFactory::TWO_FACTOR_SECRET, $user->fresh()->getAppAuthenticationSecret());
        $this->assertArrayNotHasKey('app_authentication_secret', $user->toArray());
        $this->assertArrayNotHasKey('app_authentication_recovery_codes', $user->toArray());
    }

    public function test_two_factor_columns_are_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $user->fill(['app_authentication_secret' => 'X', 'app_authentication_recovery_codes' => ['y']])->save();

        $this->assertNull($user->fresh()->getAppAuthenticationSecret());
    }

    // --- recovery command ------------------------------------------------------

    public function test_reset_command_removes_two_factor(): void
    {
        $user = User::factory()->superAdmin()->create(['email' => 'root@example.com']);

        $this->artisan('platform:reset-two-factor', ['email' => 'root@example.com'])->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->getAppAuthenticationSecret());
        $this->assertNull($user->getAppAuthenticationRecoveryCodes());
        $this->actingAs($user)->get('/admin')->assertRedirect(self::SET_UP_URL);

        $this->artisan('platform:reset-two-factor', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
