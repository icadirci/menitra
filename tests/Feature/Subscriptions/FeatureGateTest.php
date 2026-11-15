<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscription\FeatureGate;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->plan = Plan::factory()->withLimits([
            'max_locations' => 2,
            'max_menus' => null,
            'premium_themes' => 1,
            'remove_branding' => 0,
        ])->create();
    }

    private function subscribe(Tenant $tenant, Plan $plan, SubscriptionStatus $status = SubscriptionStatus::Trialing, bool $ended = false): Subscription
    {
        $subscription = app(SubscriptionService::class)->startTrial($tenant, $plan);
        $subscription->forceFill(['status' => $status, 'ended_at' => $ended ? now() : null])->save();

        return $subscription;
    }

    private function gate(?Tenant $tenant = null): FeatureGate
    {
        $this->app->make(TenantContext::class)->set($tenant ?? $this->tenant);

        return $this->app->make(FeatureGate::class);
    }

    public function test_count_limits_unlimited_and_boolean_features(): void
    {
        $this->subscribe($this->tenant, $this->plan);
        $gate = $this->gate();

        $this->assertSame(2, $gate->limit($this->tenant, PlanFeature::MaxLocations));
        $this->assertTrue($gate->allows($this->tenant, PlanFeature::MaxLocations, 1));
        $this->assertFalse($gate->allows($this->tenant, PlanFeature::MaxLocations, 2));

        $this->assertNull($gate->limit($this->tenant, PlanFeature::MaxMenus));
        $this->assertTrue($gate->allows($this->tenant, PlanFeature::MaxMenus, 10_000));

        $this->assertTrue($gate->allows($this->tenant, PlanFeature::PremiumThemes));
        $this->assertFalse($gate->allows($this->tenant, PlanFeature::RemoveBranding));
    }

    public function test_a_missing_limit_row_means_not_included(): void
    {
        $this->subscribe($this->tenant, $this->plan);
        $gate = $this->gate();

        $this->assertSame(0, $gate->limit($this->tenant, PlanFeature::MaxLanguages));
        $this->assertFalse($gate->allows($this->tenant, PlanFeature::MaxLanguages, 0));
        $this->assertFalse($gate->allows($this->tenant, PlanFeature::CustomDomain));
    }

    public function test_usage_counts_current_rows_and_ignores_soft_deleted_ones(): void
    {
        $this->subscribe($this->tenant, $this->plan);
        Location::factory()->for($this->tenant)->create();
        Location::factory()->for($this->tenant)->create()->delete();
        Location::factory()->create(); // another tenant
        $gate = $this->gate();

        $this->assertSame(1, $gate->usage($this->tenant, PlanFeature::MaxLocations));
        $this->assertTrue($gate->allows($this->tenant, PlanFeature::MaxLocations));

        Location::factory()->for($this->tenant)->create();

        $this->assertFalse($gate->allows($this->tenant, PlanFeature::MaxLocations));
    }

    public function test_ensure_can_create_throws_a_translated_exception(): void
    {
        $this->subscribe($this->tenant, $this->plan);
        Location::factory()->for($this->tenant)->count(2)->create();
        $gate = $this->gate();

        try {
            $gate->ensureCanCreate($this->tenant, PlanFeature::MaxLocations);
            $this->fail('Expected PlanLimitExceededException.');
        } catch (PlanLimitExceededException $exception) {
            $this->assertSame(PlanFeature::MaxLocations, $exception->feature);
            $this->assertSame(2, $exception->limit);
            $this->assertStringContainsString('2', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{SubscriptionStatus}>
     */
    public static function liveStatuses(): array
    {
        return [
            'trialing' => [SubscriptionStatus::Trialing],
            'active' => [SubscriptionStatus::Active],
            'past_due' => [SubscriptionStatus::PastDue],
            'grace' => [SubscriptionStatus::Grace],
        ];
    }

    #[DataProvider('liveStatuses')]
    public function test_plan_limits_apply_for_live_statuses(SubscriptionStatus $status): void
    {
        $this->subscribe($this->tenant, $this->plan, $status);

        $this->assertSame(2, $this->gate()->limit($this->tenant, PlanFeature::MaxLocations));
    }

    public function test_expired_subscription_allows_no_creation(): void
    {
        $this->subscribe($this->tenant, $this->plan, SubscriptionStatus::Expired, ended: true);
        $gate = $this->gate();

        $this->assertSame(0, $gate->limit($this->tenant, PlanFeature::MaxMenus));
        $this->assertFalse($gate->allows($this->tenant, PlanFeature::MaxLocations, 0));
        $this->assertFalse($gate->allows($this->tenant, PlanFeature::PremiumThemes));
    }

    public function test_tenant_without_subscription_allows_no_creation_and_keeps_its_data(): void
    {
        $location = Location::factory()->for($this->tenant)->create();
        $gate = $this->gate();

        $this->assertFalse($gate->allows($this->tenant, PlanFeature::MaxLocations));
        $this->expectException(PlanLimitExceededException::class);

        try {
            $gate->ensureCanCreate($this->tenant, PlanFeature::MaxLocations);
        } finally {
            // Existing records stay readable and editable.
            $location->forceFill(['phone' => '+90 212 000 00 00'])->save();
            $this->assertSame('+90 212 000 00 00', Location::query()->findOrFail($location->id)->phone);
        }
    }

    public function test_another_tenants_plan_is_never_used(): void
    {
        $other = Tenant::factory()->create();
        $generous = Plan::factory()->withLimits(['max_locations' => null])->create();
        $this->subscribe($other, $generous);
        $this->subscribe($this->tenant, $this->plan);

        $this->assertSame(2, $this->gate()->limit($this->tenant, PlanFeature::MaxLocations));
    }

    public function test_it_only_answers_for_the_tenant_in_context(): void
    {
        $other = Tenant::factory()->create();
        $gate = $this->gate();

        $this->expectException(CrossTenantWriteException::class);
        $gate->limit($other, PlanFeature::MaxLocations);
    }

    public function test_it_requires_a_context(): void
    {
        $this->expectException(CrossTenantWriteException::class);
        $this->app->make(FeatureGate::class)->limit($this->tenant, PlanFeature::MaxLocations);
    }

    public function test_features_without_a_counter_fail_loudly(): void
    {
        $this->subscribe($this->tenant, $this->plan);

        $this->expectException(LogicException::class);
        $this->gate()->usage($this->tenant, PlanFeature::PremiumThemes);
    }

    public function test_limits_are_memoised_per_request_and_can_be_flushed(): void
    {
        $this->subscribe($this->tenant, $this->plan);
        $gate = $this->gate();
        $gate->limit($this->tenant, PlanFeature::MaxLocations);

        DB::enableQueryLog();
        $gate->limit($this->tenant, PlanFeature::MaxMenus);
        $this->assertCount(0, DB::getQueryLog());

        $this->plan->limits()->where('feature', 'max_locations')->update(['limit_value' => 9]);
        $this->assertSame(2, $gate->limit($this->tenant, PlanFeature::MaxLocations));

        $gate->flush();
        $this->assertSame(9, $gate->limit($this->tenant, PlanFeature::MaxLocations));
    }

    public function test_new_registrations_start_with_a_trial_on_the_default_plan(): void
    {
        $this->seed(PlanSeeder::class);

        $this->post('/register', [
            'name' => 'Ayşe Yaman',
            'email' => 'ayse@example.com',
            'password' => 'a-Strong-passw0rd',
            'password_confirmation' => 'a-Strong-passw0rd',
            'business_name' => 'Köşe Kafe',
            'slug' => 'kose-kafe',
        ])->assertSessionHasNoErrors();

        $tenant = Tenant::query()->where('slug', 'kose-kafe')->firstOrFail();
        $gate = $this->gate($tenant);

        $this->assertSame(SubscriptionStatus::Trialing, Subscription::query()->sole()->status);
        $this->assertSame(1, $gate->limit($tenant, PlanFeature::MaxLocations));
        $this->assertFalse($gate->allows($tenant, PlanFeature::MaxLocations)); // the default location uses it
        $this->assertTrue($gate->allows($tenant, PlanFeature::MaxMenus));
    }
}
