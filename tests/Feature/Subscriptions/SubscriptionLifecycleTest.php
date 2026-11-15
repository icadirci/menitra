<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Enums\PlanFeature;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\InvalidSubscriptionTransitionException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Services\Menu\MenuCacheKeys;
use App\Services\Subscription\FeatureGate;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * SubscriptionService transitions (docs/architecture/subscriptions.md § Lifecycle)
 * and their effect on public menu access.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private const string MENU = 'http://localhost/m/konak/moda/menu';

    private Tenant $tenant;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->start = CarbonImmutable::now();
        $this->tenant = $this->subscribedTenant('konak');
        $moda = $this->location($this->tenant, 'moda', true);
        $menu = $this->menu($this->tenant, 'menu', [$moda]);
        $this->item($this->category($menu, 'Kahveler'), 'Türk Kahvesi');
        app(TenantContext::class)->set($this->tenant);
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function subscription(): Subscription
    {
        return Subscription::query()->latest('id')->firstOrFail();
    }

    private function process(): int
    {
        return $this->service()->processDue($this->subscription());
    }

    /**
     * @return list<string>
     */
    private function events(?Subscription $subscription = null): array
    {
        return SubscriptionEvent::query()
            ->where('subscription_id', ($subscription ?? $this->subscription())->getKey())
            ->orderBy('id')
            ->pluck('type')
            ->map(fn (SubscriptionEventType $type): string => $type->value)
            ->all();
    }

    /**
     * Turns the trial into a paid monthly period starting now.
     */
    private function activate(int $days = 30): Subscription
    {
        $now = CarbonImmutable::now();

        return $this->service()->activate($this->subscription(), $now, $now->addDays($days));
    }

    private function otherTenant(): Tenant
    {
        $context = app(TenantContext::class);
        $context->clear();

        try {
            return $this->subscribedTenant('baska');
        } finally {
            $context->set($this->tenant);
        }
    }

    private function graceDays(): int
    {
        return (int) config('subscriptions.grace_days');
    }

    // --- Trial → grace → expired --------------------------------------------

    public function test_trial_moves_to_grace_and_then_expires_at_the_right_times_with_the_menu_online_until_then(): void
    {
        $trialEnd = $this->start->addDays(14);

        $this->travelTo($trialEnd->subSecond());
        $this->assertSame(0, $this->process());
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscription()->status);

        $this->travelTo($trialEnd);
        $this->assertSame(1, $this->process());
        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Grace, $subscription->status);
        $this->assertTrue($subscription->grace_ends_at->equalTo($trialEnd->addDays($this->graceDays())));
        $this->get(self::MENU)->assertOk()->assertSee('Türk Kahvesi');

        $this->travelTo($subscription->grace_ends_at->subSecond());
        $this->assertSame(0, $this->process());
        $this->get(self::MENU)->assertOk();

        $this->travelTo($subscription->grace_ends_at);
        $this->assertSame(1, $this->process());
        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
        $this->assertTrue($subscription->ended_at->equalTo(CarbonImmutable::now()));

        $this->travel(1)->second();
        $this->get(self::MENU)->assertStatus(503)->assertDontSee('Türk Kahvesi');
        $this->assertSame(['trial_started', 'grace_started', 'expired'], $this->events());
    }

    public function test_grace_is_anchored_to_the_missed_boundary_so_a_late_run_never_extends_access(): void
    {
        $trialEnd = $this->start->addDays(14);

        // The scheduler was down for three days after the trial ended.
        $this->travelTo($trialEnd->addDays(3));
        $this->process();
        $this->assertTrue($this->subscription()->grace_ends_at->equalTo($trialEnd->addDays($this->graceDays())));

        // Down for longer than the grace period: both transitions in one run.
        $this->travelTo($trialEnd->addDays($this->graceDays() + 5));
        $this->assertSame(1, $this->process());
        $this->assertSame(SubscriptionStatus::Expired, $this->subscription()->status);
    }

    public function test_a_long_missed_trial_goes_to_grace_and_expired_in_one_run(): void
    {
        $this->travelTo($this->start->addDays(60));

        $this->assertSame(2, $this->process());
        $this->assertSame(SubscriptionStatus::Expired, $this->subscription()->status);
        $this->assertSame(['trial_started', 'grace_started', 'expired'], $this->events());
    }

    public function test_transitions_are_idempotent(): void
    {
        $this->travelTo($this->start->addDays(14));
        $this->process();
        $this->process();
        $this->assertSame(['trial_started', 'grace_started'], $this->events());

        $this->travel($this->graceDays())->days();
        $this->process();
        $this->assertSame(0, $this->process());
        $this->assertSame(['trial_started', 'grace_started', 'expired'], $this->events());
    }

    public function test_the_configured_grace_days_are_used(): void
    {
        config(['subscriptions.grace_days' => 3]);

        $this->travelTo($this->start->addDays(14));
        $this->process();

        $this->assertTrue($this->subscription()->grace_ends_at->equalTo($this->start->addDays(17)));
    }

    // --- Paid periods, cancellation ------------------------------------------

    public function test_an_ended_period_moves_to_grace(): void
    {
        $subscription = $this->activate();

        $this->travelTo($subscription->current_period_ends_at);
        $this->process();

        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Grace, $subscription->status);
        $this->assertTrue($subscription->grace_ends_at->equalTo($subscription->current_period_ends_at->addDays($this->graceDays())));
        $this->assertSame('period_ended', SubscriptionEvent::query()->latest('id')->first()->meta['reason']);
    }

    public function test_cancellation_stays_active_until_the_period_ends_then_moves_to_grace(): void
    {
        $subscription = $this->activate();
        $this->service()->cancel($subscription);
        $this->service()->cancel($subscription); // idempotent

        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertTrue($subscription->canceled_at->equalTo(CarbonImmutable::now()));

        $this->travelTo($subscription->current_period_ends_at->subSecond());
        $this->process();
        $this->assertSame(SubscriptionStatus::Active, $this->subscription()->status);
        $this->get(self::MENU)->assertOk();

        $this->travelTo($subscription->current_period_ends_at);
        $this->process();
        $this->assertSame(SubscriptionStatus::Grace, $this->subscription()->status);
        $this->assertSame('canceled', SubscriptionEvent::query()->latest('id')->first()->meta['reason']);
        $this->assertSame(['trial_started', 'activated', 'cancel_requested', 'grace_started'], $this->events());
    }

    public function test_a_cancellation_can_be_reverted_before_the_period_ends(): void
    {
        $subscription = $this->activate();
        $this->service()->cancel($subscription);

        $this->travel(10)->days();
        $this->service()->revertCancel($subscription);
        $this->service()->revertCancel($subscription); // idempotent

        $subscription = $this->subscription();
        $this->assertFalse($subscription->cancel_at_period_end);
        $this->assertNull($subscription->canceled_at);
        $this->assertSame(['trial_started', 'activated', 'cancel_requested', 'cancel_reverted'], $this->events());

        $this->travelTo($subscription->current_period_ends_at);
        $this->process();
        $this->assertSame('period_ended', SubscriptionEvent::query()->latest('id')->first()->meta['reason']);
    }

    public function test_cancellation_is_refused_outside_a_running_paid_period(): void
    {
        $this->expectException(InvalidSubscriptionTransitionException::class);

        $this->service()->cancel($this->subscription()); // trialing
    }

    public function test_reverting_after_the_period_ended_is_refused(): void
    {
        $subscription = $this->activate();
        $this->service()->cancel($subscription);
        $this->travelTo($subscription->current_period_ends_at);

        $this->expectException(InvalidSubscriptionTransitionException::class);

        $this->service()->revertCancel($subscription);
    }

    // --- Gateway entry points ---------------------------------------------------

    public function test_activate_renew_and_past_due_are_idempotent_and_logged_once(): void
    {
        $subscription = $this->activate();
        $this->activate(); // same period: no-op

        $this->service()->markPastDue($subscription);
        $this->service()->markPastDue($subscription);
        $this->assertSame(SubscriptionStatus::PastDue, $this->subscription()->status);

        $newEnd = $subscription->current_period_ends_at->addDays(30);
        $this->service()->renew($subscription, $newEnd);
        $this->service()->renew($subscription, $newEnd);

        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_ends_at->equalTo($newEnd));
        $this->assertSame(['trial_started', 'activated', 'payment_failed', 'renewed'], $this->events());
    }

    public function test_past_due_at_period_end_moves_to_grace_and_a_payment_in_grace_activates(): void
    {
        $subscription = $this->activate();
        $this->service()->markPastDue($subscription);

        $this->travelTo($subscription->current_period_ends_at);
        $this->process();
        $this->assertSame(SubscriptionStatus::Grace, $this->subscription()->status);
        $this->assertSame('payment_failed', SubscriptionEvent::query()->latest('id')->first()->meta['reason']);

        $this->service()->renew($subscription, $subscription->current_period_ends_at->addDays(30));
        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->grace_ends_at);
    }

    public function test_an_expired_subscription_cannot_be_activated(): void
    {
        $this->travelTo($this->start->addDays(60));
        $this->process();

        $this->expectException(InvalidSubscriptionTransitionException::class);

        $this->activate();
    }

    // --- Re-subscribing -------------------------------------------------------

    public function test_resubscribing_after_expiry_creates_a_new_row_and_keeps_the_history(): void
    {
        $this->travelTo($this->start->addDays(60));
        $this->process();
        $old = $this->subscription();
        $now = CarbonImmutable::now();
        $plan = Plan::query()->findOrFail($old->plan_id);

        $new = $this->service()->resubscribe($this->tenant, $plan, $now, $now->addMonth());
        $again = $this->service()->resubscribe($this->tenant, $plan, $now, $now->addMonth());

        $this->assertTrue($again->is($new));
        $this->assertNotSame($old->getKey(), $new->getKey());
        $this->assertSame(2, Subscription::query()->count());
        $this->assertSame(SubscriptionStatus::Expired, $old->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $new->status);
        $this->assertSame(['reactivated'], $this->events($new));
        $this->assertSame($old->getKey(), SubscriptionEvent::query()->where('subscription_id', $new->getKey())->sole()->meta['previous_subscription_id']);

        $this->get(self::MENU)->assertOk();
    }

    public function test_resubscribing_is_refused_while_a_subscription_is_live(): void
    {
        $now = CarbonImmutable::now();

        $this->expectException(InvalidSubscriptionTransitionException::class);

        $this->service()->resubscribe($this->tenant, Plan::factory()->create(), $now, $now->addMonth());
    }

    public function test_the_partial_unique_index_prevents_two_live_subscriptions(): void
    {
        $this->expectException(QueryException::class);

        (new Subscription)->forceFill([
            'plan_id' => $this->subscription()->plan_id,
            'status' => SubscriptionStatus::Active,
        ])->save();
    }

    public function test_resubscribing_requires_the_current_tenant(): void
    {
        $other = $this->otherTenant();
        $now = CarbonImmutable::now();

        $this->expectException(CrossTenantWriteException::class);

        $this->service()->resubscribe($other, Plan::factory()->create(), $now, $now->addMonth());
    }

    public function test_another_tenants_subscription_cannot_be_changed(): void
    {
        $other = $this->otherTenant();
        $foreign = Subscription::withoutTenantScope()->where('tenant_id', $other->getKey())->sole();

        $this->travelTo($this->start->addDays(60));

        try {
            $this->service()->processDue($foreign);
            $this->fail('A subscription of another tenant must not be reachable.');
        } catch (ModelNotFoundException) {
            // expected: read through the tenant scope
        }

        $this->assertSame(SubscriptionStatus::Trialing, $foreign->fresh()->status);
    }

    // --- access_until and caches ------------------------------------------------

    public function test_access_until_is_correct_for_each_status(): void
    {
        $grace = $this->graceDays();
        $this->assertTrue($this->subscription()->accessUntil()->equalTo($this->start->addDays(14 + $grace)));

        $this->travelTo($this->start->addDays(14));
        $this->process();
        $this->assertTrue($this->subscription()->accessUntil()->equalTo($this->start->addDays(14 + $grace)), 'Entering grace keeps access_until.');

        $subscription = $this->activate();
        $this->assertTrue($subscription->accessUntil()->equalTo($subscription->current_period_ends_at->addDays($grace)));

        $this->service()->markPastDue($subscription);
        $this->assertTrue($this->subscription()->accessUntil()->equalTo($subscription->current_period_ends_at->addDays($grace)));

        $this->travelTo($subscription->current_period_ends_at->addDays($grace));
        $this->process();
        $this->assertNull($this->subscription()->accessUntil());
    }

    public function test_transitions_clear_the_resolution_cache(): void
    {
        // Warm the resolution entry at the boundary (its TTL is shorter than the trial).
        $this->travelTo($this->start->addDays(14));
        $this->get(self::MENU)->assertOk();
        $this->assertTrue(Cache::has(MenuCacheKeys::slug('konak')));

        $this->process();

        $this->assertFalse(Cache::has(MenuCacheKeys::slug('konak')));
    }

    public function test_a_rolled_back_transition_keeps_the_resolution_cache_and_logs_nothing(): void
    {
        $this->travelTo($this->start->addDays(14));
        $this->get(self::MENU)->assertOk();

        DB::beginTransaction();
        $this->process();
        DB::rollBack();

        $this->assertTrue(Cache::has(MenuCacheKeys::slug('konak')));
        $this->assertSame(['trial_started'], $this->events());
    }

    public function test_expiry_takes_effect_while_the_cache_is_warm_without_the_scheduled_job(): void
    {
        $accessUntil = $this->start->addDays(14 + $this->graceDays());

        // Warm within the resolution TTL, then cross access_until without any write.
        $this->travelTo($accessUntil->subMinutes(5));
        $this->get(self::MENU)->assertOk();
        $this->assertTrue(Cache::has(MenuCacheKeys::slug('konak')));

        $this->travelTo($accessUntil->addSecond());
        $this->assertTrue(Cache::has(MenuCacheKeys::slug('konak')), 'The resolution entry is still warm.');
        $this->get(self::MENU)->assertStatus(503);
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscription()->status, 'No job ran.');
    }

    // --- FeatureGate ------------------------------------------------------------

    public function test_feature_gate_follows_transitions_in_the_same_request(): void
    {
        $gate = app(FeatureGate::class);
        $this->assertTrue($gate->allows($this->tenant, PlanFeature::MaxLocations));

        $this->travelTo($this->start->addDays(14));
        $this->process();
        $this->assertTrue($gate->allows($this->tenant, PlanFeature::MaxLocations), 'Grace still applies the plan.');

        $this->travel($this->graceDays())->days();
        $this->process();
        $this->assertSame(0, $gate->limit($this->tenant, PlanFeature::MaxLocations), 'Memoised limits were flushed.');

        $this->expectException(PlanLimitExceededException::class);
        $gate->ensureCanCreate($this->tenant, PlanFeature::MaxLocations);
    }

    public function test_expiry_never_deletes_tenant_data(): void
    {
        $counts = fn (): array => [
            DB::table('locations')->count(), DB::table('menus')->count(),
            DB::table('menu_items')->count(), DB::table('qr_codes')->count(), DB::table('subscriptions')->count(),
        ];
        $before = $counts();

        $this->travelTo($this->start->addDays(60));
        $this->process();

        $this->assertSame($before, $counts());
        $this->assertNotNull(Tenant::query()->find($this->tenant->getKey()));
    }
}
