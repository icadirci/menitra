<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CrossTenantWriteException;
use App\Filament\App\Pages\Checkout;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Billing\BillingCalculator;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PaymentProcessor;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsBilling;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * Starting a checkout (docs/architecture/subscriptions.md § Payments): who may
 * pay, for which plan and when; the amount is computed on the server.
 */
class CheckoutTest extends TestCase
{
    use BuildsBilling, BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Plan $starter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGateway();
        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $this->starter = $this->pricedPlan('starter', 30000, 240000);
        app(SubscriptionService::class)->startTrial($this->tenant, $this->starter);
        Location::factory()->for($this->tenant)->create();
    }

    public function test_an_owner_starts_a_checkout_with_a_server_computed_amount(): void
    {
        $this->billingProfile($this->tenant);

        $payment = $this->startCheckout($this->tenant, $this->starter, BillingInterval::Yearly);

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(240000, $payment->amount);
        $this->assertSame(240000, $payment->base_amount);
        $this->assertSame(BillingInterval::Yearly, $payment->interval);
        $this->assertNotNull($payment->provider_token);
        $this->assertSame('10000000146', $payment->billing_details['tax_id']);

        $request = $this->gateway->started[0];
        $this->assertSame($payment->public_id, $request->reference);
        $this->assertSame(240000, $request->amount->minor);
        $this->assertSame('85.34.78.112', $request->buyerIp);
        $this->assertSame(route('billing.iyzico.callback'), $request->callbackUrl);
        $this->assertSame(240000, array_sum(array_map(fn ($line): int => $line->amount->minor, $request->lines)));
    }

    public function test_branches_beyond_the_included_ones_are_charged(): void
    {
        $multi = $this->pricedPlan('multi-branch', 70000, null, ['included_locations' => 2]);
        Location::factory()->for($this->tenant)->count(3)->create(); // 4 branches in total
        $this->billingProfile($this->tenant);

        $payment = $this->startCheckout($this->tenant, $multi);

        $this->assertSame(2, $payment->extra_locations);
        $this->assertSame(70000, $payment->base_amount);
        $this->assertSame(70000 + 2 * 20000, $payment->amount);
        $this->assertCount(2, $this->gateway->started[0]->lines);
    }

    public function test_billing_details_are_required(): void
    {
        $this->expectException(CheckoutNotAllowedException::class);
        $this->expectExceptionMessage(__('billing.errors.billing_profile_missing'));

        $this->startCheckout($this->tenant, $this->starter);
    }

    public function test_a_hidden_plan_can_not_be_bought(): void
    {
        $hidden = $this->pricedPlan('hidden', 1000, null, ['is_public' => false]);
        $this->billingProfile($this->tenant);

        $this->expectException(CheckoutNotAllowedException::class);

        $this->startCheckout($this->tenant, $hidden);
    }

    public function test_an_active_period_is_renewed_only_within_the_window_but_upgraded_at_any_time(): void
    {
        $this->billingProfile($this->tenant);
        $pro = $this->pricedPlan('pro', 50000, 400000);
        $cheap = $this->pricedPlan('cheap', 10000);
        $this->activateMonthly(60);

        $this->inTenant($this->tenant, function () use ($cheap): void {
            $service = app(CheckoutService::class);
            $eligibility = $service->eligibility();
            $this->assertFalse($eligibility->allowed);
            $this->assertTrue($eligibility->canUpgrade);
            $this->assertSame(BillingInterval::Monthly, $eligibility->fixedInterval);
            $codes = collect($service->payablePlans($eligibility))->pluck('code')->all();
            $this->assertSame(['pro'], $codes, 'only higher plans while the renewal window is closed');
            $this->assertNotContains($cheap->code, $codes);
        });

        try {
            $this->startCheckout($this->tenant, $this->starter);
            $this->fail('Renewal outside the window was allowed.');
        } catch (CheckoutNotAllowedException $exception) {
            $this->assertStringContainsString('aktif', $exception->getMessage());
        }

        // An upgrade keeps the period's interval even when yearly is asked for.
        $upgrade = $this->startCheckout($this->tenant, $pro, BillingInterval::Yearly);
        $this->assertSame(PaymentKind::Upgrade, $upgrade->kind);
        $this->assertSame(BillingInterval::Monthly, $upgrade->interval);

        $this->travel(31)->days();

        $this->inTenant($this->tenant, function (): void {
            $this->assertTrue(app(CheckoutService::class)->eligibility()->allowed);
        });

        $renewal = $this->startCheckout($this->tenant, $this->starter, BillingInterval::Yearly);
        $this->assertNull($renewal->kind);
        $this->assertSame(BillingInterval::Yearly, $renewal->interval, 'a renewal may switch to yearly');

        $this->expectException(CheckoutNotAllowedException::class);
        $this->startCheckout($this->tenant, $cheap);
    }

    public function test_an_upgrade_charges_the_difference_for_the_remaining_days(): void
    {
        $this->freezeSecond();
        $this->billingProfile($this->tenant);
        $pro = $this->pricedPlan('pro', 50000, 400000);
        $this->activateMonthly(30);
        $this->travel(15)->days();

        $payment = $this->startCheckout($this->tenant, $pro);

        // Half of the period is left: ½ × ₺500 − ½ × ₺300.
        $this->assertSame(25000, $payment->base_amount);
        $this->assertSame(15000, $payment->credit_amount);
        $this->assertSame(10000, $payment->amount);
        $this->assertSame(15, $payment->prorated_days);
        $this->assertSame(10000, $this->gateway->started[0]->amount->minor);
        $this->assertCount(1, $this->gateway->started[0]->lines);
    }

    public function test_an_upgrade_credits_what_was_actually_paid_including_branches(): void
    {
        $multi = $this->pricedPlan('multi-branch', 70000, null, ['included_locations' => 1]);
        $top = $this->pricedPlan('top', 150000, null, ['included_locations' => 5]);
        Location::factory()->for($this->tenant)->create(); // 2 branches: 1 extra on multi-branch
        $this->billingProfile($this->tenant);

        $first = $this->startCheckout($this->tenant, $multi);
        $this->assertSame(90000, $first->amount);
        $this->gateway->paid($first->provider_token, $first->public_id, 90000);
        $this->post('/billing/iyzico/callback', ['token' => $first->provider_token]);

        // The trial (14 days) is still running: the paid period starts at its end.
        $this->travel(14)->days();
        $this->travel(15)->days();

        $upgrade = $this->startCheckout($this->tenant, $top);
        $this->inTenant($this->tenant, function () use ($upgrade): void {
            $subscription = Subscription::query()->sole();
            $period = $subscription->current_period_starts_at->diffInSeconds($subscription->current_period_ends_at);
            $remaining = CarbonImmutable::now()->diffInSeconds($subscription->current_period_ends_at);
            $this->assertSame(intdiv(90000 * (int) $remaining, (int) $period), $upgrade->credit_amount);
            $this->assertSame(intdiv(150000 * (int) $remaining, (int) $period), $upgrade->base_amount);
        });
    }

    public function test_an_upgrade_in_the_last_minutes_still_costs_the_minimum(): void
    {
        $this->billingProfile($this->tenant);
        $pro = $this->pricedPlan('pro', 50000);
        $this->activateMonthly(30);
        $this->travel(30 * 24 * 60 - 1)->minutes();

        $this->assertSame(BillingCalculator::MINIMUM_UPGRADE_AMOUNT, $this->startCheckout($this->tenant, $pro)->amount);
    }

    public function test_managers_can_not_start_a_checkout(): void
    {
        $this->billingProfile($this->tenant);
        $manager = TenantMembership::factory()->manager()->for($this->tenant)->create()->user;

        $this->expectException(AuthorizationException::class);

        $this->inTenant($this->tenant, fn () => app(CheckoutService::class)
            ->start($this->tenant, $manager, $this->starter, BillingInterval::Monthly, '127.0.0.1', 'tr'));
    }

    public function test_a_checkout_can_only_be_started_for_the_tenant_in_context(): void
    {
        $other = Tenant::factory()->create();

        $this->expectException(CrossTenantWriteException::class);

        $this->inTenant($other, fn () => app(CheckoutService::class)
            ->start($this->tenant, $this->ownerOf($this->tenant), $this->starter, BillingInterval::Monthly, '127.0.0.1', 'tr'));
    }

    public function test_a_provider_error_keeps_a_failed_payment_and_tells_the_owner(): void
    {
        $this->billingProfile($this->tenant);
        $this->gateway->failInitialize = true;

        try {
            $this->startCheckout($this->tenant, $this->starter);
            $this->fail('No exception.');
        } catch (CheckoutNotAllowedException $exception) {
            // iyzico answered with an error code: it refused the request, retrying later won't help.
            $this->assertSame(__('billing.errors.gateway_rejected'), $exception->getMessage());
        }

        $this->inTenant($this->tenant, function (): void {
            $payment = Payment::query()->sole();
            $this->assertSame(PaymentStatus::Failed, $payment->status);
            $this->assertNull($payment->provider_token);
            $this->assertSame('initialize:10051', $payment->failure_code);
        });

        $this->gateway->initializeErrorCode = null;

        try {
            $this->startCheckout($this->tenant, $this->starter);
            $this->fail('No exception.');
        } catch (CheckoutNotAllowedException $exception) {
            $this->assertSame(__('billing.errors.gateway_unavailable'), $exception->getMessage());
        }
    }

    public function test_a_complimentary_period_is_left_by_buying_a_plan_as_a_new_period(): void
    {
        $this->freezeSecond();
        $this->billingProfile($this->tenant);
        $demo = Plan::factory()->create(['code' => 'demo', 'is_public' => false]);
        $pro = $this->pricedPlan('pro', 50000, 400000);

        $this->inTenant($this->tenant, function () use ($demo): void {
            Subscription::query()->sole()->forceFill(['ended_at' => now(), 'status' => 'expired'])->save();
            app(SubscriptionService::class)->startComplimentary($this->tenant, $demo, CarbonImmutable::now()->addYears(70), 'showcase');
        });

        $this->inTenant($this->tenant, function () use ($pro): void {
            $eligibility = app(CheckoutService::class)->eligibility();
            $this->assertTrue($eligibility->allowed);
            $this->assertFalse($eligibility->isActivePeriod());
            $this->assertFalse(app(CheckoutService::class)->isUpgrade($eligibility, $pro));
        });

        // Full price for the chosen interval, not a prorated upgrade until the complimentary end.
        $payment = $this->startCheckout($this->tenant, $pro, BillingInterval::Monthly);
        $this->assertNotSame(PaymentKind::Upgrade, $payment->kind);
        $this->assertSame(50000, $payment->amount);
        // iyzico gets the billing contact (as on the receipt), not the account email.
        $this->assertSame('fatura@example.com', $this->gateway->started[0]->buyerEmail);

        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription')
            ->assertSee(__('billing.actions.choose_paid_plan'))
            ->assertDontSee(__('billing.actions.renew'));

        // Paid: the complimentary row becomes the paid plan with a period starting now.
        $this->gateway->paid($payment->provider_token, $payment->public_id, 50000);
        app(TenantContext::class)->clear();

        $this->inTenant($this->tenant, function () use ($payment, $pro): void {
            app(PaymentProcessor::class)->complete(Payment::query()->findOrFail($payment->getKey()));

            $subscription = Subscription::query()->whereNull('ended_at')->sole();
            $this->assertSame($pro->getKey(), $subscription->plan_id);
            $this->assertFalse($subscription->isComplimentary());
            $this->assertTrue($subscription->current_period_starts_at->equalTo(CarbonImmutable::now()));
            $this->assertTrue($subscription->current_period_ends_at->equalTo(CarbonImmutable::now()->addMonthNoOverflow()));
            $this->assertSame(PaymentStatus::Succeeded, Payment::query()->findOrFail($payment->getKey())->status);
        });
    }

    public function test_checkout_attempts_are_limited(): void
    {
        $this->billingProfile($this->tenant);

        for ($i = 0; $i < 10; $i++) {
            $this->startCheckout($this->tenant, $this->starter);
        }

        $this->expectException(CheckoutNotAllowedException::class);
        $this->expectExceptionMessage(__('billing.errors.too_many_attempts'));

        $this->startCheckout($this->tenant, $this->starter);
    }

    public function test_the_owner_sees_the_checkout_page_and_other_roles_do_not(): void
    {
        $this->billingProfile($this->tenant);

        // Yearly is preselected and its advantage spelled out: 8 months instead of 12.
        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription/checkout')
            ->assertOk()
            ->assertSee(__('billing.checkout.sections.summary'))
            ->assertSee('₺2.400')
            ->assertSee('₺200')
            ->assertSee(trans_choice('billing.checkout.yearly_perk_months', 4, ['paid' => 8, 'free' => 4]))
            ->assertSee(__('billing.checkout.saving', ['amount' => '₺1.200', 'percent' => 33]))
            ->assertSee(__('billing.checkout.pay_amount', ['amount' => '₺2.400,00']))
            ->assertSee(__('billing.checkout.trust.card'));

        $manager = TenantMembership::factory()->manager()->for($this->tenant)->create()->user;
        $this->actingAs($manager)->get('/app/yaman-kahvesi/subscription/checkout')->assertForbidden();
    }

    public function test_the_subscription_page_links_to_the_checkout(): void
    {
        app(TenantContext::class)->clear();

        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription')
            ->assertOk()
            ->assertSee(__('billing.actions.pay'))
            ->assertSee(__('billing.history.heading'));
    }

    public function test_the_page_switches_plans_and_periods_and_pays(): void
    {
        $this->billingProfile($this->tenant);
        $this->pricedPlan('pro', 50000, 400000);
        $this->enter();

        Livewire::test(Checkout::class)
            ->assertSet('data.interval', 'yearly')
            ->assertSee('₺2.400')
            ->set('data.interval', 'monthly')
            ->assertSee(__('billing.checkout.switch_yearly_hint', ['amount' => '₺1.200', 'percent' => 33]))
            ->assertSeeHtml('aria-checked="true"')
            ->call('selectPlan', 'pro')
            ->assertSet('data.plan', 'pro')
            ->call('selectPlan', 'not-a-plan')
            ->assertSet('data.plan', 'pro')
            ->assertSee(__('billing.checkout.pay_amount', ['amount' => '₺500,00']))
            ->set('data.terms', true)
            ->call('pay')
            ->assertRedirect('https://sandbox-cpp.iyzipay.com/?token=fake');

        $this->assertSame(50000, $this->gateway->started[0]->amount->minor);
    }

    public function test_the_page_requires_the_terms(): void
    {
        $this->billingProfile($this->tenant);
        $this->enter();

        Livewire::test(Checkout::class)
            ->call('pay')
            ->assertHasErrors(['data.terms']);

        $this->assertSame([], $this->gateway->started);
    }

    public function test_an_active_subscription_sees_the_upgrade_offer(): void
    {
        $this->freezeSecond();
        $this->billingProfile($this->tenant);
        $this->pricedPlan('pro', 50000, 400000);
        // 45 of 60 days left: the renewal window is closed, only the upgrade is offered.
        $this->activateMonthly(60);
        $this->travel(15)->days();
        app(TenantContext::class)->clear();

        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription/checkout')
            ->assertOk()
            ->assertSee(__('billing.checkout.title_upgrade'))
            ->assertSee(__('billing.checkout.upgrade_today', ['amount' => '₺150', 'days' => 45]))
            ->assertSee(__('billing.checkout.summary.upgrade_credit'))
            ->assertSee('₺225,00');

        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription')
            ->assertSee(__('billing.actions.upgrade'));
    }

    public function test_the_page_pays_an_upgrade_although_the_interval_field_is_hidden(): void
    {
        $this->freezeSecond();
        $this->billingProfile($this->tenant);
        $this->pricedPlan('pro', 50000, 400000);
        $this->activateMonthly(60);
        $this->travel(15)->days();
        app(TenantContext::class)->clear();
        $this->enter();

        Livewire::test(Checkout::class)
            ->call('selectPlan', 'pro')
            ->set('data.terms', true)
            ->call('pay')
            ->assertRedirect('https://sandbox-cpp.iyzipay.com/?token=fake');

        $this->assertCount(1, $this->gateway->started);
        $this->assertSame(15000, $this->gateway->started[0]->amount->minor);
    }

    private function activateMonthly(int $days): void
    {
        $this->inTenant($this->tenant, function () use ($days): void {
            $now = CarbonImmutable::now();
            $price = $this->starter->priceFor(BillingInterval::Monthly);
            app(SubscriptionService::class)->activate(Subscription::query()->sole(), $now, $now->addDays($days), $price);
        });
    }

    private function enter(): void
    {
        app(TenantContext::class)->clear();
        $this->actingAs($this->ownerOf($this->tenant));
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        app(TenantContext::class)->set($this->tenant);
    }
}
