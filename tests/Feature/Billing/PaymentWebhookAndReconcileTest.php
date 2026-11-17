<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsBilling;
use Tests\Support\BuildsMenus;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * Payments the browser never returned from: the signed webhook and the
 * reconciliation command settle them through the same PaymentProcessor.
 */
class PaymentWebhookAndReconcileTest extends TestCase
{
    use BuildsBilling, BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Plan $starter;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->fakeGateway();
        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $this->starter = $this->pricedPlan('starter', 30000);
        app(SubscriptionService::class)->startTrial($this->tenant, $this->starter);
        Location::factory()->for($this->tenant)->create();
        $this->billingProfile($this->tenant);
    }

    private function paymentStatus(Payment $payment): PaymentStatus
    {
        return $this->inTenant($this->tenant, fn (): PaymentStatus => Payment::query()->findOrFail($payment->getKey())->status);
    }

    private function subscriptionStatus(): SubscriptionStatus
    {
        return $this->inTenant($this->tenant, fn (): SubscriptionStatus => Subscription::query()->sole()->status);
    }

    /**
     * @return array<string, string|int>
     */
    private function webhookPayload(Payment $payment, string $status = 'SUCCESS'): array
    {
        return [
            'paymentConversationId' => $payment->public_id,
            'merchantId' => '3397951',
            'token' => $payment->provider_token,
            'status' => $status,
            'iyziReferenceCode' => 'ref-1',
            'iyziEventType' => 'CHECKOUT_FORM_AUTH',
            'iyziEventTime' => 1763460000000,
            'iyziPaymentId' => 28461234,
        ];
    }

    /**
     * @param  array<string, string|int>  $payload
     */
    private function signature(array $payload): string
    {
        $secret = FakePaymentGateway::WEBHOOK_SECRET;

        return hash_hmac('sha256', $secret.$payload['iyziEventType'].$payload['iyziPaymentId'].$payload['token'].$payload['paymentConversationId'].$payload['status'], $secret);
    }

    public function test_a_signed_webhook_applies_the_payment_from_the_providers_answer(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 30000);
        $payload = $this->webhookPayload($payment);

        $this->postJson('/billing/iyzico/webhook', $payload, ['X-IYZ-SIGNATURE-V3' => $this->signature($payload)])->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $this->paymentStatus($payment));
        $this->assertSame(SubscriptionStatus::Active, $this->subscriptionStatus());
    }

    public function test_the_webhook_payload_status_is_never_trusted(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        // The provider's API says it was not paid, whatever the notification claims.
        $payload = $this->webhookPayload($payment);

        $this->postJson('/billing/iyzico/webhook', $payload, ['X-IYZ-SIGNATURE-V3' => $this->signature($payload)])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $this->paymentStatus($payment));
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionStatus());
    }

    public function test_an_invalid_signature_is_refused(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 30000);
        $payload = $this->webhookPayload($payment);

        $this->postJson('/billing/iyzico/webhook', $payload, ['X-IYZ-SIGNATURE-V3' => str_repeat('a', 64)])->assertForbidden();
        $this->postJson('/billing/iyzico/webhook', $payload)->assertForbidden();
        $this->postJson('/billing/iyzico/webhook', ['token' => $payment->provider_token])->assertForbidden();

        $this->assertSame(0, $this->gateway->retrieveCalls);
        $this->assertSame(PaymentStatus::Pending, $this->paymentStatus($payment));
    }

    public function test_a_signed_webhook_for_an_unknown_or_mismatched_payment_is_acknowledged_and_ignored(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $payload = ['paymentConversationId' => 'someone-else'] + $this->webhookPayload($payment);

        $this->postJson('/billing/iyzico/webhook', $payload, ['X-IYZ-SIGNATURE-V3' => $this->signature($payload)])->assertOk();

        $payload = ['token' => 'unknown-token-1234567890'] + $this->webhookPayload($payment);
        $this->postJson('/billing/iyzico/webhook', $payload, ['X-IYZ-SIGNATURE-V3' => $this->signature($payload)])->assertOk();

        $this->assertSame(0, $this->gateway->retrieveCalls);
    }

    public function test_reconciliation_applies_paid_checkouts_and_expires_abandoned_ones(): void
    {
        $paid = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($paid->provider_token, $paid->public_id, 30000);
        $abandoned = $this->startCheckout($this->tenant, $this->starter);

        $this->artisan('billing:reconcile-payments')->assertSuccessful();
        $this->assertSame(PaymentStatus::Pending, $this->paymentStatus($paid), 'fresh checkouts are left alone');

        $this->travel(10)->minutes();
        $this->artisan('billing:reconcile-payments')->assertSuccessful();
        $this->assertSame(PaymentStatus::Succeeded, $this->paymentStatus($paid));
        $this->assertSame(PaymentStatus::Pending, $this->paymentStatus($abandoned));

        $this->travel(2)->hours();
        $this->artisan('billing:reconcile-payments')->assertSuccessful();
        $this->assertSame(PaymentStatus::Expired, $this->paymentStatus($abandoned));
        $this->assertSame(SubscriptionStatus::Active, $this->subscriptionStatus());
    }

    public function test_reconciliation_skips_suspended_tenants(): void
    {
        $payment = $this->startCheckout($this->tenant, $this->starter);
        $this->gateway->paid($payment->provider_token, $payment->public_id, 30000);
        $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();
        $this->travel(10)->minutes();

        $this->artisan('billing:reconcile-payments')->assertSuccessful();

        $this->assertSame(0, $this->gateway->retrieveCalls);
    }
}
