<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Enums\BillingInterval;
use App\Enums\PlanFeature;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscription\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_limits_for_every_feature_on_every_plan(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertSame([config('subscriptions.default_plan'), 'pro', 'multi-branch'], Plan::query()->orderBy('sort_order')->pluck('code')->all());

        foreach (Plan::query()->with('limits')->get() as $plan) {
            $this->assertEqualsCanonicalizing(
                array_map(fn (PlanFeature $feature): string => $feature->value, PlanFeature::cases()),
                $plan->limits->map(fn (PlanLimit $limit): string => $limit->feature->value)->all(),
                $plan->code,
            );
        }
    }

    public function test_it_seeds_the_three_public_plans(): void
    {
        $this->seed(PlanSeeder::class);

        $plans = Plan::query()->with(['prices', 'limits'])->get()->keyBy('code');
        $limit = fn (string $code, PlanFeature $feature): ?int => $plans[$code]->limits->firstWhere('feature', $feature)->limit_value;
        $price = fn (string $code, BillingInterval $interval) => $plans[$code]->priceFor($interval);

        // Starter: 300 TL a month, basic themes only.
        $starter = config('subscriptions.default_plan');
        $this->assertSame(30_000, $price($starter, BillingInterval::Monthly)->amount);
        $this->assertSame(240_000, $price($starter, BillingInterval::Yearly)->amount);
        $this->assertSame(0, $limit($starter, PlanFeature::PremiumThemes));
        $this->assertSame(1, $limit($starter, PlanFeature::MaxLocations));

        // Professional: every theme, the recommended plan.
        $this->assertSame(50_000, $price('pro', BillingInterval::Monthly)->amount);
        $this->assertSame(400_000, $price('pro', BillingInterval::Yearly)->amount);
        $this->assertSame(1, $limit('pro', PlanFeature::PremiumThemes));
        $this->assertTrue($plans['pro']->is_featured);
        $this->assertSame(1, Plan::query()->where('is_featured', true)->count());

        // Multi-branch: unlimited branches, two included, 200 TL per further branch.
        $this->assertNull($limit('multi-branch', PlanFeature::MaxLocations));
        $this->assertSame(2, $plans['multi-branch']->included_locations);
        $this->assertSame(70_000, $price('multi-branch', BillingInterval::Monthly)->amount);
        $this->assertSame(20_000, $price('multi-branch', BillingInterval::Monthly)->extra_location_amount);
        $this->assertSame(560_000, $price('multi-branch', BillingInterval::Yearly)->amount);
        $this->assertSame(160_000, $price('multi-branch', BillingInterval::Yearly)->extra_location_amount);
        $this->assertNull($price('pro', BillingInterval::Monthly)->extra_location_amount);
    }

    public function test_re_running_never_duplicates_and_keeps_subscriptions(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('code', config('subscriptions.default_plan'))->sole();
        $tenant = Tenant::factory()->create();
        $subscription = app(SubscriptionService::class)->startTrial($tenant, $plan);

        $this->seed(PlanSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->assertSame(3, Plan::query()->count());
        $this->assertSame(6, DB::table('plan_translations')->count());
        $this->assertSame(6, DB::table('plan_prices')->count());
        $this->assertSame(3 * count(PlanFeature::cases()), PlanLimit::query()->count());
        $this->assertSame(1, Subscription::withoutTenantScope()->count());
        $this->assertSame($plan->id, Subscription::withoutTenantScope()->sole()->plan_id);
        $this->assertSame($subscription->id, Subscription::withoutTenantScope()->sole()->id);
    }

    public function test_the_database_rejects_negative_limits_and_duplicate_features(): void
    {
        $plan = Plan::factory()->create();
        $plan->limits()->create(['feature' => PlanFeature::MaxMenus, 'limit_value' => 1]);

        try {
            DB::transaction(fn () => $plan->limits()->create(['feature' => PlanFeature::MaxMenus, 'limit_value' => 2]));
            $this->fail('Duplicate feature accepted.');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        DB::transaction(fn () => $plan->limits()->create(['feature' => PlanFeature::MaxItems, 'limit_value' => -1]));
    }
}
