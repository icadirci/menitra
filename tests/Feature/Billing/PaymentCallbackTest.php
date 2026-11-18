<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Enums\PlanFeature;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CrossTenantWriteException;
use App\Mail\PaymentReceiptMail;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Services\Billing\PaymentProcessor;
use App\Services\Subscription\FeatureGate;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsBilling;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * The provider's browser callback and PaymentProcessor: only the provider's
 * verified answer changes anything, exactly once, through SubscriptionService.
 */
class PaymentCallbackTest extends TestCase
{
    use BuildsBilling, BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Plan $starter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        Mail::fake();
        $this->fakeGateway();
        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $this->starter = $this->pricedPlan('starter', 30000, 240000);
        app(SubscriptionService::class)->startTrial($this->tenant, $this->starter);
        Location::factory()->for($this->tenant)->create();
        $this->billingProfile($this->tenant);
    }

    private function subscription(): Subscription
    {
        return $this->inTenant($this->tenant, fn (): Subscription => Subscription::query()->latest('id')->firstOrFail());
    }

    private function fresh(Payment $payment): Payment
    {
        return $this->inTenant($this->tenant, fn (): Payment => Payment::query()->findOrFail($payment->getKey()));
    }

    private function returnFromProvider(Payment $payment)
    {
        return $this->post('/billing/iyzico/callback', ['token' => $payment->provider_token]);
    }

    public function test_a_paid_trial_is_activated_from_the_trial_end_and_a_receipt_is_sent(): void
    {
        $trialEndsAt = $this->subscription()->trial_ends_at;
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 30000);

        $this->returnFromProvider($payment)
            ->assertStatus(303)
            ->assertRedirect('http://localhost/app/yaman-kahvesi/subscription?payment='.$payment->public_id)
            ->assertCookieMissing(config('session.cookie'));

        $payment = $this->fresh($payment);
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame(PaymentKind::Activation, $payment->kind);
        $this->assertSame('28461234', $payment->provider_payment_id);
        $this->assertSame('0008', $payment->card_last_four);
        $this->assertMatchesRegularExpression('/^RST-\d{4}-\d{6}$/', $payment->receipt_number);

        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_starts_at->equalTo($trialEndsAt));
        $this->assertTrue($subscription->current_period_ends_at->equalTo($trialEndsAt->addMonthNoOverflow()));
        $this->assertSame($payment->plan_price_id, $subscription->plan_price_id);
        $this->assertSame('iyzico', $subscription->provider);
        $this->assertSame($subscription->id, $payment->subscription_id);

        Mail::assertQueued(PaymentReceiptMail::class, fn (PaymentReceiptMail $mail): bool => $mail->hasTo('fatura@example.com')
            && $mail->receiptNumber === $payment->receipt_number
            && $mail->amountMinor === 30000);
    }

    public function test_a_repeated_callback_never_applies_a_payment_twice(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 30000);

        $this->returnFromProvider($payment);
        $endsAt = $this->subscription()->current_period_ends_at;
        $this->returnFromProvider($payment);
        $this->inTenant($this->tenant, fn () => app(PaymentProcessor::class)->complete($this->fresh($payment)));

        $this->assertTrue($this->subscription()->current_period_ends_at->equalTo($endsAt));
        $this->assertSame(1, $this->inTenant($this->tenant, fn (): int => SubscriptionEvent::query()->where('type', SubscriptionEventType::Activated)->count()));
        Mail::assertQueuedCount(1);
    }

    public function test_a_declined_payment_changes_nothing(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->declined($payment->provider_token, $payment->public_id);

        $this->returnFromProvider($payment)->assertRedirect('http://localhost/app/yaman-kahvesi/subscription?payment='.$payment->public_id);

        $payment = $this->fresh($payment);
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('10051', $payment->failure_code);
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscription()->status);
        Mail::assertNothingQueued();
    }

    public function test_an_amount_that_does_not_match_is_never_applied(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 100);

        $this->returnFromProvider($payment);

        $this->assertSame('verification_failed', $this->fresh($payment)->failure_code);
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscription()->status);
    }

    public function test_a_result_for_another_payment_reference_is_never_applied(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, '01JOTHERPAYMENTREFERENCE00', 30000);

        $this->returnFromProvider($payment);

        $this->assertSame(PaymentStatus::Failed, $this->fresh($payment)->status);
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscription()->status);
    }

    public function test_an_unpaid_checkout_stays_pending_until_it_is_stale(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);

        $this->returnFromProvider($payment);
        $this->assertSame(PaymentStatus::Pending, $this->fresh($payment)->status);

        $this->travel(121)->minutes();
        $this->returnFromProvider($payment);
        $this->assertSame(PaymentStatus::Expired, $this->fresh($payment)->status);
    }

    public function test_an_unreachable_provider_leaves_the_payment_pending(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->failRetrieve = true;

        $this->returnFromProvider($payment)->assertRedirect('http://localhost/app/yaman-kahvesi/subscription?payment='.$payment->public_id);

        $this->assertSame(PaymentStatus::Pending, $this->fresh($payment)->status);
    }

    public function test_unknown_or_malformed_tokens_reveal_nothing(): void
    {
        $this->post('/billing/iyzico/callback', ['token' => 'unknown-token-1234567890'])->assertRedirect('/app');
        $this->post('/billing/iyzico/callback', ['token' => "x' OR 1=1"])->assertRedirect('/app');
        $this->post('/billing/iyzico/callback')->assertRedirect('/app');

        $this->assertSame(0, $this->gateway->retrieveCalls);
    }

    public function test_paying_during_an_active_period_extends_it_from_its_end(): void
    {
        $now = CarbonImmutable::now();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)->activate(Subscription::query()->sole(), $now, $now->addDays(10)));

        $payment = $this->startCheckout($this->tenant, $this->starter, BillingInterval::Yearly);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 240000);
        $this->returnFromProvider($payment);

        $subscription = $this->subscription();
        $this->assertSame(PaymentKind::Renewal, $this->fresh($payment)->kind);
        $this->assertTrue($subscription->current_period_starts_at->equalTo($now->addDays(10)));
        $this->assertTrue($subscription->current_period_ends_at->equalTo($now->addDays(10)->addMonthsNoOverflow(12)));
        $this->assertSame($payment->plan_price_id, $subscription->plan_price_id);
    }

    public function test_paying_in_grace_for_another_plan_changes_the_plan_and_starts_now(): void
    {
        $pro = $this->pricedPlan('pro', 50000);
        $this->travel(15)->days();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)->processDue(Subscription::query()->sole()));
        $this->assertSame(SubscriptionStatus::Grace, $this->subscription()->status);

        $payment = $this->startCheckout($this->tenant, $pro);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 50000);
        $this->returnFromProvider($payment);

        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($pro->id, $subscription->plan_id);
        $this->assertTrue($subscription->current_period_starts_at->equalTo(CarbonImmutable::now()));
        $this->assertNull($subscription->grace_ends_at);
        $this->assertSame(1, $this->inTenant($this->tenant, fn (): int => SubscriptionEvent::query()->where('type', SubscriptionEventType::PlanChanged)->count()));
    }

    public function test_paying_after_expiry_starts_a_new_subscription_and_keeps_the_history(): void
    {
        $this->travel(30)->days();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)->processDue(Subscription::query()->sole()));
        $expired = $this->subscription();
        $this->assertSame(SubscriptionStatus::Expired, $expired->status);

        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 30000);
        $this->returnFromProvider($payment);

        $subscription = $this->subscription();
        $this->assertNotSame($expired->id, $subscription->id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(PaymentKind::Resubscription, $this->fresh($payment)->kind);
        $this->assertSame(2, $this->inTenant($this->tenant, fn (): int => Subscription::query()->count()));
    }

    public function test_a_payment_is_processed_only_in_its_own_tenant_context(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $other = Tenant::factory()->create();

        $this->expectException(CrossTenantWriteException::class);

        $this->inTenant($other, fn () => app(PaymentProcessor::class)->complete($payment));
    }

    public function test_the_subscription_page_reports_the_result_and_lists_the_receipt(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 30000);
        $this->returnFromProvider($payment);
        $payment = $this->fresh($payment);
        app(TenantContext::class)->clear();

        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription?payment='.$payment->public_id)
            ->assertOk()
            ->assertSee(__('billing.result.succeeded.title'))
            ->assertSee($payment->receipt_number);

        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription/receipt?payment='.$payment->public_id)
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee('Ayşe Yaman')
            ->assertSee(__('billing.receipt.not_invoice'));
    }

    public function test_another_tenants_receipt_is_not_found(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 30000);
        $this->returnFromProvider($payment);
        app(TenantContext::class)->clear();

        $other = Tenant::factory()->create(['slug' => 'baska-kafe']);
        $this->pricedPlan('other');

        $this->actingAs($this->ownerOf($other))
            ->get('/app/baska-kafe/subscription/receipt?payment='.$payment->public_id)
            ->assertNotFound();

        $this->actingAs($this->ownerOf($other))
            ->get('/app/baska-kafe/subscription?payment='.$payment->public_id)
            ->assertOk()
            ->assertDontSee(__('billing.result.succeeded.title'));
    }

    public function test_a_paid_upgrade_switches_the_plan_at_once_and_keeps_the_period(): void
    {
        $pro = $this->pricedPlan('pro', 50000, null, [], ['premium_themes' => 1]);
        $now = CarbonImmutable::now();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)->activate(Subscription::query()->sole(), $now, $now->addDays(30), $this->starter->priceFor(BillingInterval::Monthly)));
        $this->travel(10)->days();

        $payment = $this->startCheckout($this->tenant, $pro);
        $this->gateway->paid($payment->provider_token, $payment->public_id, $payment->amount);
        $this->returnFromProvider($payment);

        $subscription = $this->subscription();
        $this->assertSame($pro->id, $subscription->plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_ends_at->equalTo($now->addDays(30)), 'the period is unchanged');
        $this->assertSame($pro->priceFor(BillingInterval::Monthly)->id, $subscription->plan_price_id);

        $payment = $this->fresh($payment);
        $this->assertSame(PaymentKind::Upgrade, $payment->kind);
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => app(FeatureGate::class)->allows($this->tenant, PlanFeature::PremiumThemes)));

        $event = $this->inTenant($this->tenant, fn () => SubscriptionEvent::query()->where('type', SubscriptionEventType::PlanChanged)->sole());
        $this->assertSame('upgrade', $event->meta['mode']);
        $this->assertSame('pro', $event->meta['to_plan']);

        app(TenantContext::class)->clear();
        $this->actingAs($this->ownerOf($this->tenant))
            ->get('/app/yaman-kahvesi/subscription/receipt?payment='.$payment->public_id)
            ->assertOk()
            ->assertSee(__('billing.checkout.summary.upgrade_credit'));
    }

    public function test_an_upgrade_paid_after_the_period_ended_still_raises_the_plan(): void
    {
        $pro = $this->pricedPlan('pro', 50000);
        $now = CarbonImmutable::now();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)->activate(Subscription::query()->sole(), $now, $now->addDays(30), $this->starter->priceFor(BillingInterval::Monthly)));
        $this->travel(29)->days();
        $payment = $this->startCheckout($this->tenant, $pro);
        $this->travel(2)->days();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)->processDue(Subscription::query()->sole()));

        $this->gateway->paid($payment->provider_token, $payment->public_id, $payment->amount);
        $this->returnFromProvider($payment);

        $subscription = $this->subscription();
        $this->assertSame(SubscriptionStatus::Grace, $subscription->status);
        $this->assertSame($pro->id, $subscription->plan_id);
        $this->assertSame(PaymentStatus::Succeeded, $this->fresh($payment)->status);
    }
}
