<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\MenuType;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantRegistrationService;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::factory()->create(['code' => config('subscriptions.default_plan'), 'trial_days' => 14]);
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ayşe Yaman',
            'email' => 'ayse@example.com',
            'password' => 'a-Strong-passw0rd',
            'password_confirmation' => 'a-Strong-passw0rd',
            'business_name' => 'Yaman Kahvesi',
            'slug' => 'yaman-kahvesi',
        ], $overrides);
    }

    public function test_registration_page_loads(): void
    {
        $this->get('/register')->assertOk()->assertSee(__('auth.register.title'));
    }

    public function test_registration_creates_the_whole_business_in_one_go(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload())->assertRedirect('/app');

        $user = User::query()->where('email', 'ayse@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->platform_role);

        $tenant = Tenant::query()->where('slug', 'yaman-kahvesi')->sole();
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('Yaman Kahvesi', $tenant->getFilamentName());

        $membership = $user->memberships()->sole();
        $this->assertSame($tenant->id, $membership->tenant_id);
        $this->assertSame(TenantRole::Owner, $membership->role);
        $this->assertTrue($membership->is_active);

        app(TenantContext::class)->set($tenant);

        $location = Location::query()->sole();
        $this->assertTrue($location->is_default);
        $this->assertSame(config('tenancy.default_location_slug'), $location->slug);
        $this->assertSame('Yaman Kahvesi', $location->translate()->name);

        $this->assertSame('tr', $tenant->default_locale);
        $this->assertSame(['tr'], $tenant->enabledLocales());
        $this->assertSame(1, $tenant->locales()->count());

        $menu = Menu::query()->sole();
        $this->assertSame(MenuType::AllDay, $menu->type);
        $this->assertFalse($menu->is_published);
        $this->assertSame([$location->id], $menu->locations()->pluck('locations.id')->all());

        $subscription = Subscription::query()->sole();
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame($this->plan->id, $subscription->plan_id);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $subscription->trial_ends_at->timestamp, 5);
        $this->assertNull($subscription->ended_at);

        $event = SubscriptionEvent::query()->sole();
        $this->assertSame(SubscriptionEventType::TrialStarted, $event->type);
        $this->assertSame(SubscriptionStatus::Trialing, $event->to_status);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registered_owner_must_verify_their_email_before_entering_the_panel(): void
    {
        $this->post('/register', $this->payload());

        $this->get('/app/yaman-kahvesi')->assertRedirect('http://localhost/email/verify');

        // Verify the instance the session guard holds (signed in by registration).
        auth()->user()->markEmailAsVerified();

        $this->get('/app')->assertRedirect('http://localhost/app/yaman-kahvesi');
        $this->get('/app/yaman-kahvesi')->assertOk()->assertSee('Yaman Kahvesi');
    }

    public function test_new_businesses_default_to_turkish_whatever_the_ui_language(): void
    {
        app()->setLocale('en');

        $this->post('/register', $this->payload());

        $this->assertSame('tr', Tenant::query()->sole()->default_locale);
    }

    public function test_public_registration_can_never_grant_a_platform_role_or_pick_a_tenant(): void
    {
        $other = Tenant::factory()->create();

        $this->post('/register', $this->payload([
            'platform_role' => 'super_admin',
            'tenant_id' => (string) $other->id,
            'role' => 'manager',
        ]));

        $user = User::query()->where('email', 'ayse@example.com')->sole();
        $this->assertNull($user->platform_role);
        $this->assertSame(TenantRole::Owner, $user->memberships()->sole()->role);
        $this->assertNotSame($other->id, $user->memberships()->sole()->tenant_id);

        $this->get('/admin')->assertForbidden();
    }

    public function test_registration_is_atomic(): void
    {
        $this->mock(SubscriptionService::class)
            ->shouldReceive('startTrial')
            ->andThrow(new RuntimeException('Simulated failure after most rows were written.'));

        try {
            app(TenantRegistrationService::class)->register('Ayşe', 'ayse@example.com', 'secret-password', 'Yaman Kahvesi', 'yaman-kahvesi', 'tr');
            $this->fail('Expected the registration to fail.');
        } catch (RuntimeException) {
            // expected
        }

        foreach (['users', 'tenants', 'tenant_translations', 'tenant_locales', 'tenant_user', 'locations', 'menus', 'location_menu', 'subscriptions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertFalse(app(TenantContext::class)->check());
    }

    public function test_slug_is_derived_from_the_business_name_when_left_empty(): void
    {
        $this->post('/register', $this->payload(['slug' => '', 'business_name' => 'Çay Ocağı Şişli']));

        $this->assertDatabaseHas('tenants', ['slug' => 'cay-ocagi-sisli']);
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $this->post('/register', $this->payload(['slug' => 'admin']))->assertSessionHasErrors('slug');
        $this->post('/register', $this->payload(['slug' => 'ozellikler']))->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_invalid_slug_format_is_rejected(): void
    {
        // Five attempts: the sixth would hit the registration rate limit (5/min).
        foreach (['ab', 'bad_slug', '-leading', 'double--hyphen', str_repeat('a', 41)] as $slug) {
            $this->post('/register', $this->payload(['slug' => $slug, 'email' => uniqid().'@example.com']))
                ->assertSessionHasErrors('slug');
        }
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_taken_slug_and_email_are_rejected(): void
    {
        Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        User::factory()->create(['email' => 'ayse@example.com']);

        $this->post('/register', $this->payload())->assertSessionHasErrors(['slug', 'email']);
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->post('/register', $this->payload(['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');
    }

    public function test_registration_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/register', $this->payload(['slug' => 'admin']))->assertSessionHasErrors('slug');
        }

        $this->post('/register', $this->payload())->assertStatus(429);
    }

    public function test_signed_in_users_are_sent_to_the_panel_instead_of_registering(): void
    {
        $this->actingAs(User::factory()->create())->get('/register')->assertRedirect('/app');
    }

    public function test_registration_is_not_served_on_tenant_subdomains(): void
    {
        $this->get('http://acme.localhost/register')->assertNotFound();
        $this->post('http://acme.localhost/register', $this->payload())->assertNotFound();
    }
}
