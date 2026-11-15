<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

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

    public function test_it_seeds_placeholder_limits_for_every_feature(): void
    {
        $this->seed(PlanSeeder::class);

        $plan = Plan::query()->where('code', config('subscriptions.default_plan'))->sole();

        $this->assertEqualsCanonicalizing(
            array_map(fn (PlanFeature $feature): string => $feature->value, PlanFeature::cases()),
            $plan->limits->map(fn (PlanLimit $limit): string => $limit->feature->value)->all(),
        );
    }

    public function test_re_running_never_duplicates_and_keeps_subscriptions(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->sole();
        $tenant = Tenant::factory()->create();
        $subscription = app(SubscriptionService::class)->startTrial($tenant, $plan);

        $this->seed(PlanSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->assertSame(1, Plan::query()->count());
        $this->assertSame(2, DB::table('plan_translations')->count());
        $this->assertSame(count(PlanFeature::cases()), PlanLimit::query()->count());
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
