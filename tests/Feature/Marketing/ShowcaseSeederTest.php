<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\InvalidSubscriptionTransitionException;
use App\Jobs\ProcessTenantSubscriptionLifecycle;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ShowcaseSeeder (launch plan Faz 6): the theme showcase businesses are safe
 * to seed in production — no members, a complimentary subscription that the
 * lifecycle never ends, idempotent.
 */
class ShowcaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function slugs(): array
    {
        return array_values((array) config('marketing.showcase'));
    }

    /**
     * @return array<string, Subscription>
     */
    private function subscriptions(): array
    {
        return Subscription::withoutTenantScope()
            ->with('plan')
            ->whereIn('tenant_id', Tenant::query()->whereIn('slug', $this->slugs())->select('id'))
            ->get()
            ->keyBy(fn (Subscription $subscription) => Tenant::query()->findOrFail($subscription->tenant_id)->slug)
            ->all();
    }

    public function test_every_showcase_business_is_created_without_members(): void
    {
        $this->seed(ShowcaseSeeder::class);

        $tenants = Tenant::query()->whereIn('slug', $this->slugs())->get();

        $this->assertCount(5, $tenants);
        $this->assertSame(0, TenantMembership::query()->count());
        $this->assertSame(0, User::query()->count());

        foreach ($this->slugs() as $slug) {
            $this->get("/m/{$slug}")->assertOk();
        }
    }

    public function test_each_business_has_a_complimentary_subscription_with_an_event(): void
    {
        $this->seed(ShowcaseSeeder::class);

        $subscriptions = $this->subscriptions();
        $this->assertCount(5, $subscriptions);

        foreach ($subscriptions as $subscription) {
            $this->assertSame(SubscriptionStatus::Active, $subscription->status);
            $this->assertSame(ShowcaseSeeder::PLAN_CODE, $subscription->plan->code);
            $this->assertNull($subscription->plan_price_id);
            $this->assertNull($subscription->provider);
            $this->assertTrue($subscription->current_period_ends_at->equalTo(CarbonImmutable::parse(ShowcaseSeeder::ACCESS_UNTIL, 'UTC')));

            $events = SubscriptionEvent::withoutTenantScope()->where('subscription_id', $subscription->getKey())->get();
            $this->assertCount(1, $events);
            $this->assertSame(SubscriptionEventType::ComplimentaryStarted, $events->first()->type);
            $this->assertSame('showcase', $events->first()->meta['reason']);
        }

        $this->assertFalse(Plan::query()->where('code', ShowcaseSeeder::PLAN_CODE)->sole()->is_public);
    }

    public function test_the_lifecycle_never_ends_the_showcase(): void
    {
        $this->seed(ShowcaseSeeder::class);

        // Well past the demo plan's 365-day trial and any grace period.
        $this->travelTo(CarbonImmutable::parse('2090-06-01 12:00:00', 'UTC'));

        Queue::fake();
        $this->artisan('subscriptions:process-lifecycle')->assertSuccessful();
        Queue::assertNotPushed(ProcessTenantSubscriptionLifecycle::class);

        // The tenant job itself (as if dispatched) changes nothing either.
        $context = app(TenantContext::class);
        foreach (Tenant::query()->whereIn('slug', $this->slugs())->get() as $tenant) {
            $context->set($tenant);
            (new ProcessTenantSubscriptionLifecycle)->handle(app(SubscriptionService::class));
            $context->clear();
        }

        foreach ($this->subscriptions() as $subscription) {
            $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        }
        $this->assertSame(5, SubscriptionEvent::withoutTenantScope()->count());

        foreach ($this->slugs() as $slug) {
            $this->get("/m/{$slug}")->assertOk();
        }
    }

    public function test_seeding_again_changes_nothing(): void
    {
        $this->seed(ShowcaseSeeder::class);
        $counts = [Tenant::query()->count(), Subscription::withoutTenantScope()->count(), QrCode::withoutTenantScope()->count(), SubscriptionEvent::withoutTenantScope()->count()];

        $this->seed(ShowcaseSeeder::class);

        $this->assertSame($counts, [Tenant::query()->count(), Subscription::withoutTenantScope()->count(), QrCode::withoutTenantScope()->count(), SubscriptionEvent::withoutTenantScope()->count()]);
        $this->assertFalse(app(TenantContext::class)->check());
    }

    public function test_an_existing_demo_plan_is_not_overwritten(): void
    {
        $plan = Plan::factory()->withLimits(['max_locations' => 9, 'premium_themes' => 1])->create(['code' => ShowcaseSeeder::PLAN_CODE, 'trial_days' => 30]);

        $this->seed(ShowcaseSeeder::class);

        $plan->refresh();
        $this->assertSame(30, $plan->trial_days);
        $this->assertSame(9, (int) $plan->limits()->where('feature', 'max_locations')->value('limit_value'));
        $this->assertSame(1, Plan::query()->where('code', ShowcaseSeeder::PLAN_CODE)->count());
    }

    public function test_the_demo_seeder_adds_the_team_only_locally(): void
    {
        $this->seed(DemoSeeder::class);

        $konak = Tenant::query()->where('slug', 'kahve-konagi')->sole();
        $this->assertSame(3, TenantMembership::query()->where('tenant_id', $konak->getKey())->count());

        $others = Tenant::query()->whereIn('slug', array_diff($this->slugs(), ['kahve-konagi']))->pluck('id');
        $this->assertSame(0, TenantMembership::query()->whereIn('tenant_id', $others)->count());

        $this->seed(DemoSeeder::class);
        $this->assertSame(3, TenantMembership::query()->where('tenant_id', $konak->getKey())->count());
    }

    public function test_businesses_without_members_break_no_admin_page(): void
    {
        $this->seed(ShowcaseSeeder::class);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/plans')->assertOk();
        $this->artisan('ops:health-check')->assertSuccessful();
    }

    public function test_a_complimentary_subscription_requires_the_current_tenant(): void
    {
        $this->seed(ShowcaseSeeder::class);
        $tenant = Tenant::factory()->create();
        $plan = Plan::query()->where('code', ShowcaseSeeder::PLAN_CODE)->sole();

        $this->expectException(CrossTenantWriteException::class);
        app(SubscriptionService::class)->startComplimentary($tenant, $plan, CarbonImmutable::parse(ShowcaseSeeder::ACCESS_UNTIL), 'showcase');
    }

    public function test_a_complimentary_subscription_is_idempotent_and_refused_over_a_live_one(): void
    {
        $plan = Plan::factory()->create();
        $endsAt = CarbonImmutable::now()->addYears(5)->startOfSecond();
        $service = app(SubscriptionService::class);
        $context = app(TenantContext::class);

        $free = Tenant::factory()->create();
        $context->set($free);
        $first = $service->startComplimentary($free, $plan, $endsAt, 'showcase');
        $this->assertSame($first->getKey(), $service->startComplimentary($free, $plan, $endsAt, 'showcase')->getKey());
        $this->assertSame(1, SubscriptionEvent::query()->count());

        $trialing = Tenant::factory()->create();
        $context->set($trialing);
        $service->startTrial($trialing, $plan);

        $this->expectException(InvalidSubscriptionTransitionException::class);
        $service->startComplimentary($trialing, $plan, $endsAt, 'showcase');
    }
}
