<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CrossTenantWriteException;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Billing\CheckoutService;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_an_active_period_can_be_renewed_only_within_the_window_and_only_on_its_plan(): void
    {
        $this->billingProfile($this->tenant);
        $pro = $this->pricedPlan('pro', 50000);
        $this->inTenant($this->tenant, function (): void {
            $now = CarbonImmutable::now();
            app(SubscriptionService::class)->activate(Subscription::query()->sole(), $now, $now->addDays(60));
        });

        $this->inTenant($this->tenant, function (): void {
            $this->assertFalse(app(CheckoutService::class)->eligibility()->allowed);
        });

        try {
            $this->startCheckout($this->tenant, $this->starter);
            $this->fail('Renewal outside the window was allowed.');
        } catch (CheckoutNotAllowedException) {
        }

        $this->travel(31)->days();

        $this->inTenant($this->tenant, function (): void {
            $eligibility = app(CheckoutService::class)->eligibility();
            $this->assertTrue($eligibility->allowed);
            $this->assertFalse($eligibility->canChangePlan);
        });

        $this->assertSame($this->starter->id, $this->startCheckout($this->tenant, $this->starter)->plan_id);

        $this->expectException(CheckoutNotAllowedException::class);
        $this->startCheckout($this->tenant, $pro);
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
            $this->assertSame(__('billing.errors.gateway_unavailable'), $exception->getMessage());
        }

        $this->inTenant($this->tenant, function (): void {
            $payment = Payment::query()->sole();
            $this->assertSame(PaymentStatus::Failed, $payment->status);
            $this->assertNull($payment->provider_token);
            $this->assertSame('initialize:10051', $payment->failure_code);
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

        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription/checkout')
            ->assertOk()
            ->assertSee(__('billing.checkout.pay'))
            ->assertSee('₺300');

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
}
