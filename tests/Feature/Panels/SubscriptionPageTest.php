<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\SubscriptionStatus;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
    }

    private function owner(): User
    {
        return TenantMembership::factory()->for($this->tenant)->create()->user;
    }

    public function test_owner_sees_plan_status_and_usage(): void
    {
        $plan = Plan::factory()->withLimits(['max_locations' => 3, 'max_menus' => null, 'premium_themes' => 0])->create();
        $plan->translations()->where('locale', 'tr')->update(['name' => 'Başlangıç Paketi']);
        app(SubscriptionService::class)->startTrial($this->tenant, $plan);
        Location::factory()->for($this->tenant)->count(2)->create();

        $this->actingAs($this->owner())
            ->get('/app/yaman-kahvesi/subscription')
            ->assertOk()
            ->assertSee('Başlangıç Paketi')
            ->assertSee(__('plans.statuses.trialing'))
            ->assertSee(__('plans.usage', ['used' => 2, 'limit' => 3]))
            ->assertSee(__('plans.unlimited'));
    }

    public function test_without_a_live_subscription_the_page_says_so(): void
    {
        $this->actingAs($this->owner())
            ->get('/app/yaman-kahvesi/subscription')
            ->assertOk()
            ->assertSee(__('panels.app.subscription.none.title'));
    }

    public function test_managers_are_forbidden(): void
    {
        $membership = TenantMembership::factory()->for($this->tenant)->manager()->create();
        $membership->locations()->attach(Location::factory()->for($this->tenant)->create());

        $this->actingAs($membership->user)->get('/app/yaman-kahvesi/subscription')->assertForbidden();
    }

    public function test_access_until_follows_the_lifecycle_rules(): void
    {
        config(['subscriptions.grace_days' => 7]);
        $this->app->make(TenantContext::class)->set($this->tenant);
        $plan = Plan::factory()->create();
        $now = CarbonImmutable::parse('2026-11-15 12:00:00');

        $subscription = new Subscription;
        $subscription->forceFill([
            'tenant_id' => $this->tenant->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => $now,
        ]);
        $this->assertTrue($now->addDays(7)->equalTo($subscription->accessUntil()));

        $subscription->forceFill(['status' => SubscriptionStatus::PastDue, 'current_period_ends_at' => $now->addMonth()]);
        $this->assertTrue($now->addMonth()->addDays(7)->equalTo($subscription->accessUntil()));

        $subscription->forceFill(['status' => SubscriptionStatus::Grace, 'grace_ends_at' => $now->addDays(3)]);
        $this->assertTrue($now->addDays(3)->equalTo($subscription->accessUntil()));

        $subscription->forceFill(['status' => SubscriptionStatus::Expired, 'ended_at' => $now]);
        $this->assertNull($subscription->accessUntil());
    }
}
