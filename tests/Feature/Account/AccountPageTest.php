<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\SubscriptionStatus;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Dashboard\BusinessDashboard;
use App\Services\Menu\MenuUrlGenerator;
use App\Services\Subscription\FeatureGate;
use App\Services\Subscription\SubscriptionService;
use App\Support\MarketingUrl;
use App\Tenancy\ResolvedTenantFactory;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * /account (overview.md § Authentication): minimal, read-only, central domain
 * only, tenant from the user's verified membership.
 */
class AccountPageTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    private QrCode $modaQr;

    private QrCode $cihangirQr;

    protected function setUp(): void
    {
        parent::setUp();

        config(['subscriptions.grace_days' => 7]);
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));

        $this->tenant = $this->subscribedTenant('konak');
        $this->renameTenant($this->tenant, 'Kahve Konağı');
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        $this->modaQr = $this->qr($this->tenant, $this->moda, 'Moda masa');
        $this->cihangirQr = $this->qr($this->tenant, $this->cihangir, 'Cihangir masa');
    }

    // ---------------------------------------------------------------- helpers

    private function renameTenant(Tenant $tenant, string $name): void
    {
        $tenant->translations()->update(['name' => $name]);
    }

    private function qr(Tenant $tenant, Location $location, string $label, bool $active = true): QrCode
    {
        return $this->inTenant($tenant, fn (): QrCode => QrCode::query()->create([
            'location_id' => $location->getKey(),
            'label' => $label,
            'is_active' => $active,
        ]));
    }

    private function owner(?Tenant $tenant = null, ?User $user = null): User
    {
        $factory = TenantMembership::factory()->for($tenant ?? $this->tenant);

        return ($user !== null ? $factory->for($user) : $factory)->create()->user;
    }

    private function member(string $role, Location ...$locations): User
    {
        $membership = TenantMembership::factory()->for($this->tenant)->{$role}()->create();
        $membership->locations()->attach(array_map(fn (Location $location) => $location->getKey(), $locations));

        return $membership->user;
    }

    private function subscription(): Subscription
    {
        return $this->inTenant($this->tenant, fn (): Subscription => Subscription::query()->latest('id')->firstOrFail());
    }

    private function processDue(): void
    {
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)->processDue($this->subscription()));
    }

    /**
     * A fresh request's view of the scoped services.
     */
    private function account(User $user, string $uri = '/account'): TestResponse
    {
        foreach ([TenantContext::class, BusinessDashboard::class, FeatureGate::class] as $abstract) {
            app()->forgetInstance($abstract);
        }

        return $this->actingAs($user)->get($uri);
    }

    private function date(?CarbonImmutable $date): string
    {
        return $date->setTimezone('Europe/Istanbul')->locale('tr')->isoFormat('LL');
    }

    // ------------------------------------------------------- access & routing

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/account')->assertRedirect('http://localhost/login');
    }

    public function test_unverified_users_are_sent_to_the_verification_notice(): void
    {
        $user = $this->owner(user: User::factory()->unverified()->create());

        $this->account($user)->assertRedirect(route('verification.notice'));
    }

    public function test_page_is_404_on_a_tenant_subdomain(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get('http://konak.localhost/account')->assertNotFound();
        $this->actingAs($owner)->get('http://konak.localhost/account/qr/'.$this->modaQr->code.'.svg')->assertNotFound();
    }

    public function test_page_is_private_and_not_indexed(): void
    {
        $response = $this->account($this->owner())->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex')
            ->assertSee('<meta name="robots" content="noindex">', escape: false);
    }

    public function test_page_is_read_only(): void
    {
        $this->actingAs($this->owner())->post('/account')->assertStatus(405);
    }

    // --------------------------------------------------- subscription states

    public function test_trialing_owner_sees_plan_trial_end_access_until_and_online(): void
    {
        $subscription = $this->subscription();

        $this->account($this->owner())
            ->assertOk()
            ->assertSee('Kahve Konağı')
            ->assertSee(__('plans.statuses.trialing'))
            ->assertSee(__('account.subscription.key_dates.trial_ends_at'))
            ->assertSee($this->date($subscription->trial_ends_at))
            ->assertSee('14 gün kaldı')
            ->assertSee($this->date($subscription->accessUntil()))
            ->assertSee('data-online="true"', escape: false)
            ->assertSee(__('account.menu.online'))
            ->assertDontSee('data-test="grace-warning"', escape: false)
            ->assertDontSee('data-test="expired-message"', escape: false);
    }

    public function test_active_owner_sees_the_period_end(): void
    {
        $periodEnd = CarbonImmutable::now()->addMonth();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)
            ->activate($this->subscription(), CarbonImmutable::now(), $periodEnd));

        $this->account($this->owner())
            ->assertOk()
            ->assertSee(__('plans.statuses.active'))
            ->assertSee(__('account.subscription.key_dates.current_period_ends_at'))
            ->assertSee($this->date($periodEnd))
            ->assertSee($this->date($periodEnd->addDays(7)))
            ->assertSee('data-online="true"', escape: false);
    }

    public function test_past_due_owner_sees_the_failed_payment_note_and_stays_online(): void
    {
        $periodEnd = CarbonImmutable::now()->addMonth();
        $this->inTenant($this->tenant, function () use ($periodEnd): void {
            $service = app(SubscriptionService::class);
            $service->activate($this->subscription(), CarbonImmutable::now(), $periodEnd);
            $service->markPastDue($this->subscription());
        });

        $this->account($this->owner())
            ->assertOk()
            ->assertSee(__('plans.statuses.past_due'))
            ->assertSee(__('account.subscription.past_due'))
            ->assertSee($this->date($periodEnd))
            ->assertSee('data-online="true"', escape: false);
    }

    public function test_cancellation_at_period_end_is_mentioned(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $service = app(SubscriptionService::class);
            $service->activate($this->subscription(), CarbonImmutable::now(), CarbonImmutable::now()->addMonth());
            $service->cancel($this->subscription());
        });

        $this->account($this->owner())->assertOk()->assertSee(__('account.subscription.cancel_at_period_end'));
    }

    public function test_grace_shows_a_prominent_warning_with_the_grace_end(): void
    {
        $this->travel(15)->days();
        $this->processDue();
        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Grace, $subscription->status);

        $this->account($this->owner())
            ->assertOk()
            ->assertSee('data-test="grace-warning"', escape: false)
            ->assertSee('role="alert"', escape: false)
            ->assertSee(__('account.grace.title'))
            ->assertSee(__('account.grace.body', ['date' => $this->date($subscription->grace_ends_at)]))
            ->assertSee(__('plans.statuses.grace'))
            ->assertSee(__('account.subscription.key_dates.grace_ends_at'))
            ->assertSee('data-online="true"', escape: false)
            ->assertDontSee('data-test="expired-message"', escape: false);
    }

    public function test_expired_shows_menu_closed_data_kept_and_the_reactivation_path(): void
    {
        $this->travel(30)->days();
        $this->processDue();
        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);

        $this->account($this->owner())
            ->assertOk()
            ->assertSee('data-test="expired-message"', escape: false)
            ->assertSee(__('account.expired.title'))
            ->assertSee(__('account.expired.body'))
            ->assertSee(__('account.expired.reactivate'))
            ->assertSee('href="'.MarketingUrl::to('contact').'"', escape: false)
            ->assertSee(__('plans.statuses.expired'))
            ->assertSee($this->date($subscription->ended_at))
            ->assertSee('data-online="false"', escape: false)
            ->assertSee(__('account.menu.offline'))
            ->assertDontSee('data-test="grace-warning"', escape: false)
            ->assertDontSee('data-test="key-date"', escape: false)
            ->assertDontSee('data-test="access-until"', escape: false);
    }

    public function test_expired_shows_the_support_details_from_site_settings(): void
    {
        $this->travel(30)->days();
        $this->processDue();
        $settings = SiteSetting::current();
        $settings->update(['contact_email' => 'destek@restano.test']);
        $settings->translations()->create(['locale' => 'tr', 'support_hours' => 'Hafta içi 09:00–18:00']);

        $this->account($this->owner())
            ->assertOk()
            ->assertSee('mailto:destek@restano.test', escape: false)
            ->assertSee('Hafta içi 09:00–18:00');
    }

    public function test_without_any_subscription_the_menu_is_offline_and_reactivation_is_offered(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'yeni']);
        $this->location($tenant, 'merkez', true);

        $this->account($this->owner($tenant))
            ->assertOk()
            ->assertSee(__('account.subscription.none'))
            ->assertSee('data-online="false"', escape: false)
            ->assertSee('data-test="expired-message"', escape: false)
            ->assertDontSee('data-test="key-date"', escape: false);
    }

    public function test_online_state_follows_the_public_menu_rule_even_before_the_job_runs(): void
    {
        // Trial (14 days) and grace (7 days) are over, but processDue() hasn't run.
        $this->travel(22)->days();
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscription()->status);

        $this->account($this->owner())
            ->assertOk()
            ->assertSee('data-online="false"', escape: false)
            ->assertSee('data-test="expired-message"', escape: false);

        $this->assertFalse(app(ResolvedTenantFactory::class)->make($this->tenant->fresh())->isOnline());
    }

    public function test_exact_access_until_boundary_is_still_online(): void
    {
        $this->travelTo($this->subscription()->accessUntil());

        $this->account($this->owner())->assertOk()->assertSee('data-online="true"', escape: false);

        $this->travel(1)->seconds();

        $this->account($this->owner())->assertOk()->assertSee('data-online="false"', escape: false);
    }

    // ------------------------------------------------------- links & QR codes

    public function test_owner_links_come_from_the_menu_url_generator_and_the_stable_qr_url(): void
    {
        $urls = app(MenuUrlGenerator::class);

        $this->account($this->owner())
            ->assertOk()
            ->assertSee('href="'.$urls->tenant('konak').'"', escape: false)
            ->assertSee('href="'.$urls->location('konak', 'moda').'"', escape: false)
            ->assertSee('href="'.$urls->qr($this->modaQr->code).'"', escape: false)
            ->assertSee('http://localhost/q/'.$this->modaQr->code)
            ->assertSee('href="'.route('account.qr', ['code' => $this->modaQr->code, 'format' => 'svg']).'"', escape: false)
            ->assertSee('href="'.route('account.qr', ['code' => $this->modaQr->code, 'format' => 'png']).'"', escape: false)
            ->assertSee('data:image/svg+xml;base64,', escape: false)
            ->assertSee('href="http://localhost/app/konak"', escape: false);
    }

    public function test_owner_sees_only_the_default_branch(): void
    {
        $this->account($this->owner())
            ->assertOk()
            ->assertSee($this->modaQr->code)
            ->assertDontSee($this->cihangirQr->code)
            ->assertDontSee(app(MenuUrlGenerator::class)->location('konak', 'cihangir'));
    }

    public function test_the_default_qr_code_is_the_oldest_active_one(): void
    {
        $this->modaQr->forceFill(['is_active' => false])->save();
        $newer = $this->qr($this->tenant, $this->moda, 'Moda yeni');

        $this->account($this->owner())
            ->assertOk()
            ->assertSee($newer->code)
            ->assertDontSee($this->modaQr->code);
    }

    public function test_a_branch_without_an_active_qr_code_says_so(): void
    {
        $this->modaQr->forceFill(['is_active' => false])->save();

        $this->account($this->owner())->assertOk()->assertSee(__('account.links.no_qr'));
    }

    // -------------------------------------------------------- role visibility

    public function test_manager_sees_online_state_and_own_branches_but_no_subscription_details(): void
    {
        $urls = app(MenuUrlGenerator::class);

        $this->account($this->member('manager', $this->cihangir))
            ->assertOk()
            ->assertSee('Kahve Konağı')
            ->assertSee('data-online="true"', escape: false)
            ->assertSee('href="'.$urls->location('konak', 'cihangir').'"', escape: false)
            ->assertSee($urls->qr($this->cihangirQr->code))
            ->assertDontSee($urls->location('konak', 'moda'))
            ->assertDontSee($this->modaQr->code)
            ->assertDontSee('data-test="public-menu-url"', escape: false)
            ->assertDontSee('data-test="subscription"', escape: false)
            ->assertDontSee(__('plans.statuses.trialing'))
            ->assertDontSee($this->date($this->subscription()->trial_ends_at))
            ->assertSee('href="http://localhost/app/konak"', escape: false);
    }

    public function test_branch_staff_sees_only_their_branches(): void
    {
        $this->account($this->member('branchStaff', $this->moda))
            ->assertOk()
            ->assertSee($this->modaQr->code)
            ->assertDontSee($this->cihangirQr->code)
            ->assertDontSee('data-test="subscription"', escape: false);
    }

    public function test_member_without_assigned_branches_sees_no_links(): void
    {
        $this->account($this->member('branchStaff'))
            ->assertOk()
            ->assertSee(__('account.links.none'))
            ->assertDontSee('data-test="branch"', escape: false);
    }

    public function test_non_owners_see_only_that_the_menu_is_offline_when_expired(): void
    {
        $this->travel(30)->days();
        $this->processDue();

        $this->account($this->member('manager', $this->cihangir))
            ->assertOk()
            ->assertSee('data-online="false"', escape: false)
            ->assertSee(__('account.staff_offline'))
            ->assertDontSee('data-test="expired-message"', escape: false)
            ->assertDontSee(__('plans.statuses.expired'));
    }

    public function test_non_owners_get_no_grace_warning(): void
    {
        $this->travel(15)->days();
        $this->processDue();

        $this->account($this->member('branchStaff', $this->moda))
            ->assertOk()
            ->assertSee('data-online="true"', escape: false)
            ->assertDontSee('data-test="grace-warning"', escape: false);
    }

    // ------------------------------------------------------------ QR download

    public function test_owner_downloads_the_qr_code_as_svg_and_png(): void
    {
        $owner = $this->owner();

        $svg = $this->account($owner, '/account/qr/'.$this->modaQr->code.'.svg')->assertOk();
        $svg->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('attachment; filename="qr-moda-masa-'.strtolower($this->modaQr->code).'.svg"', (string) $svg->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg', $svg->getContent());
        $this->assertStringContainsString('no-store', (string) $svg->headers->get('Cache-Control'));

        $png = $this->account($owner, '/account/qr/'.$this->modaQr->code.'.png')->assertOk();
        $png->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());
    }

    public function test_owner_may_download_codes_of_any_branch_of_their_tenant(): void
    {
        $this->account($this->owner(), '/account/qr/'.$this->cihangirQr->code.'.svg')->assertOk();
    }

    public function test_qr_download_follows_the_qr_code_policy(): void
    {
        $staff = $this->member('branchStaff', $this->moda);

        $this->account($staff, '/account/qr/'.$this->modaQr->code.'.png')->assertOk();
        $this->account($staff, '/account/qr/'.$this->cihangirQr->code.'.png')->assertForbidden();
    }

    public function test_another_tenants_qr_code_is_404(): void
    {
        $other = $this->subscribedTenant('baska');
        $otherQr = $this->qr($other, $this->location($other, 'merkez', true), 'Başka');

        $this->account($this->owner(), '/account/qr/'.$otherQr->code.'.svg')->assertNotFound();
    }

    public function test_unknown_formats_and_malformed_codes_are_404(): void
    {
        $owner = $this->owner();

        $this->account($owner, '/account/qr/'.$this->modaQr->code.'.gif')->assertNotFound();
        $this->account($owner, '/account/qr/ABC.svg')->assertNotFound();
        $this->account($owner, '/account/qr/ZZZZZZZZZZ.svg')->assertNotFound();
    }

    public function test_qr_download_requires_a_verified_signed_in_member(): void
    {
        $this->get('/account/qr/'.$this->modaQr->code.'.svg')->assertRedirect('http://localhost/login');
        $this->account(User::factory()->create(), '/account/qr/'.$this->modaQr->code.'.svg')->assertForbidden();
    }

    // --------------------------------------------------------- tenant choice

    public function test_member_of_two_tenants_sees_only_the_first_membership(): void
    {
        $owner = $this->owner();
        $other = $this->subscribedTenant('baska');
        $this->renameTenant($other, 'Başka Fırın');
        $otherQr = $this->qr($other, $this->location($other, 'merkez', true), 'Başka');
        $this->owner($other, $owner);

        foreach (['/account', '/account?tenant='.$other->getKey(), '/account?tenant=baska&tenant_id='.$other->getKey()] as $uri) {
            $this->account($owner, $uri)
                ->assertOk()
                ->assertSee('Kahve Konağı')
                ->assertDontSee('Başka Fırın')
                ->assertDontSee($otherQr->code)
                ->assertDontSee('baska.localhost');
        }

        // The other tenant's code can't be downloaded from /account either.
        $this->account($owner, '/account/qr/'.$otherQr->code.'.svg?tenant=baska')->assertNotFound();
    }

    public function test_inactive_memberships_are_skipped(): void
    {
        $user = User::factory()->create();
        TenantMembership::factory()->for($this->tenant)->for($user)->inactive()->create();
        $other = $this->subscribedTenant('baska');
        $this->renameTenant($other, 'Başka Fırın');
        $this->location($other, 'merkez', true);
        $this->owner($other, $user);

        $this->account($user)->assertOk()->assertSee('Başka Fırın')->assertDontSee('Kahve Konağı');
    }

    public function test_an_active_tenant_is_preferred_over_an_older_suspended_one(): void
    {
        $user = User::factory()->create();
        $suspended = Tenant::factory()->suspended()->create(['slug' => 'kapali']);
        $this->renameTenant($suspended, 'Kapalı İşletme');
        $this->owner($suspended, $user);
        $this->owner(user: $user);

        $this->account($user)->assertOk()->assertSee('Kahve Konağı')->assertDontSee('Kapalı İşletme');
    }

    public function test_member_of_only_a_suspended_tenant_gets_an_explanation_and_no_data(): void
    {
        $this->tenant->forceFill(['status' => 'suspended'])->save();

        $this->account($this->owner())
            ->assertForbidden()
            ->assertSee(__('account.unavailable.suspended.title'))
            ->assertSee(__('tenancy.suspended'))
            ->assertSee('href="'.MarketingUrl::to('contact').'"', escape: false)
            ->assertDontSee('Kahve Konağı')
            ->assertDontSee($this->modaQr->code);

        $this->assertFalse(app(TenantContext::class)->check());
    }

    public function test_qr_download_is_refused_for_a_suspended_tenant(): void
    {
        $owner = $this->owner();
        $this->tenant->forceFill(['status' => 'suspended'])->save();

        $this->account($owner, '/account/qr/'.$this->modaQr->code.'.svg')->assertForbidden();
    }

    public function test_platform_user_without_membership_is_sent_to_the_admin_panel(): void
    {
        $this->account(User::factory()->superAdmin()->create())->assertRedirect('/admin');
    }

    public function test_user_without_membership_or_platform_role_gets_an_explanation(): void
    {
        $this->account(User::factory()->create())
            ->assertForbidden()
            ->assertSee(__('account.unavailable.no_business.title'))
            ->assertSee(__('account.unavailable.no_business.body'));
    }

    public function test_platform_user_with_a_membership_sees_the_membership(): void
    {
        $this->account($this->owner(user: User::factory()->superAdmin()->create()))
            ->assertOk()
            ->assertSee('Kahve Konağı');
    }

    // ----------------------------------------------------- bundles and links

    public function test_page_loads_only_the_public_bundle_and_no_livewire(): void
    {
        $hot = storage_path('framework/testing/vite-account.hot');
        File::ensureDirectoryExists(dirname($hot));
        File::put($hot, 'http://vite.test');

        try {
            $this->withVite();
            app(Vite::class)->useHotFile($hot);
            SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest = false;

            $this->account($this->owner())
                ->assertOk()
                ->assertSee('http://vite.test/resources/css/app.css', escape: false)
                ->assertSee('http://vite.test/resources/js/app.js', escape: false)
                ->assertDontSee('menu-base', escape: false)
                ->assertDontSee('resources/js/menu.js', escape: false)
                ->assertDontSee('livewire', escape: false)
                ->assertDontSee('filament', escape: false);
        } finally {
            File::delete($hot);
        }
    }

    public function test_marketing_nav_links_signed_in_users_to_the_account(): void
    {
        $this->get('/')->assertOk()
            ->assertSee(__('marketing.nav.login'))
            ->assertDontSee('href="'.route('account').'"', escape: false);

        $this->actingAs($this->owner())->get('/')->assertOk()
            ->assertSee('href="'.route('account').'"', escape: false)
            ->assertSee(__('marketing.nav.account'));
    }

    public function test_account_copy_has_the_same_keys_in_every_locale(): void
    {
        $keys = fn (string $locale): array => array_keys(Arr::dot(require lang_path("{$locale}/account.php")));

        $this->assertSame($keys('tr'), $keys('en'));
    }
}
