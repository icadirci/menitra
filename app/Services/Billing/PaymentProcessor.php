<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PaymentGatewayException;
use App\Filament\App\Pages\ViewReceipt;
use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\ShopOrder;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Shop\ShopOrderService;
use App\Services\Subscription\SubscriptionService;
use App\Support\Billing\CheckoutOutcome;
use App\Support\Billing\CheckoutResult;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Applies the provider's verified result of a checkout
 * (docs/architecture/subscriptions.md § Payments). Called by the browser
 * callback, the webhook job and the reconciliation job; all three may race,
 * so the payment row is locked and a succeeded payment is never applied twice.
 *
 * Success → one SubscriptionService transition, chosen from the live
 * subscription when the money arrives (not when the checkout started):
 * - none (expired)          → resubscribe(), period from now
 * - trialing                → [changePlan()] + activate(), period from the trial end
 * - grace / past due        → [changePlan()] + activate(), period from now
 * - active                  → renew(), period from the current period end (an extension)
 * - a shop order's payment → no subscription change; ShopOrderService::markPaid()
 * - an upgrade payment      → upgrade(): the plan changes at once, the period stays
 *
 * Bank transfers never reach the provider: approveBankTransfer() applies them
 * the same way once a super admin confirmed the money (closeBankTransfer()
 * rejects or cancels them).
 */
class PaymentProcessor
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PaymentGateway $gateway,
        private readonly SubscriptionService $subscriptions,
        private readonly ShopOrderService $shopOrders,
    ) {}

    /**
     * @throws PaymentGatewayException when the provider can't be asked (retry later)
     */
    public function complete(Payment $payment): Payment
    {
        $this->ensureCurrentTenant($payment);

        if ($payment->isSucceeded() || $payment->provider_token === null) {
            return $payment;
        }

        $result = $this->gateway->retrieveCheckout($payment->provider_token, $payment->public_id);

        $applied = DB::transaction(function () use ($payment, $result): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isSucceeded()) {
                return $locked;
            }

            match ($result->outcome) {
                CheckoutOutcome::Succeeded => $this->succeed($locked, $result),
                CheckoutOutcome::Failed => $this->fail($locked, $result->errorCode ?? 'declined', $result->errorMessage),
                CheckoutOutcome::Pending => $this->expireIfStale($locked),
            };

            return $locked;
        });

        $payment->setRawAttributes($applied->getAttributes(), true);

        return $payment;
    }

    private function succeed(Payment $payment, CheckoutResult $result): void
    {
        $expected = $payment->money();

        if ($result->reference !== $payment->public_id
            || $result->basketId !== $payment->public_id
            || $result->paidAmount === null
            || ! $result->paidAmount->equals($expected)) {
            // Money may have been taken: never apply, flag for manual review (refund in the iyzico panel).
            Log::critical('Payment result does not match the checkout; not applied.', [
                'payment' => $payment->public_id,
                'tenant_id' => $payment->tenant_id,
                'provider_payment_id' => $result->paymentId,
            ]);
            $this->fail($payment, 'verification_failed', null);

            return;
        }

        $this->settle($payment, $result->paymentId, $result->cardAssociation, $result->cardFamily, $result->cardLastFour);
    }

    /**
     * A super admin saw a bank transfer arrive (PaymentAdminService, in the
     * payment's own TenantContext): applied exactly like a verified card
     * payment, at most once. The transition is chosen from the live
     * subscription now, so the period starts when the money is confirmed.
     *
     * @throws ValidationException when the transfer is no longer open
     */
    public function approveBankTransfer(Payment $payment): Payment
    {
        $this->ensureCurrentTenant($payment);

        return DB::transaction(function () use ($payment): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isOpenBankTransfer()) {
                throw ValidationException::withMessages(['payment' => __('billing.bank_transfer.errors.not_open')]);
            }

            $this->settle($locked, null, null, null, null);

            return $locked;
        });
    }

    /**
     * Ends an open bank transfer without applying it: rejected by a super
     * admin (the money didn't arrive) or cancelled by the owner before
     * reporting it ($requireUnreported). A shop order's payment fails the order.
     *
     * @throws ValidationException when the transfer is no longer open (or already reported)
     */
    public function closeBankTransfer(Payment $payment, string $code, ?string $message, bool $requireUnreported = false): Payment
    {
        $this->ensureCurrentTenant($payment);

        return DB::transaction(function () use ($payment, $code, $message, $requireUnreported): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isOpenBankTransfer() || ($requireUnreported && $locked->transfer_notified_at !== null)) {
                throw ValidationException::withMessages(['payment' => __('billing.bank_transfer.errors.not_open')]);
            }

            $this->fail($locked, $code, $message);

            return $locked;
        });
    }

    /**
     * Records a payment as succeeded and applies it: a shop order becomes
     * paid, a plan payment goes through one SubscriptionService transition.
     * Card details are null for bank transfers.
     */
    private function settle(Payment $payment, ?string $providerPaymentId, ?string $cardAssociation, ?string $cardFamily, ?string $cardLastFour): void
    {
        if ($payment->isShopOrder()) {
            $this->succeedShopOrder($payment, $providerPaymentId, $cardAssociation, $cardFamily, $cardLastFour);

            return;
        }

        $tenant = $this->context->tenant();
        $plan = Plan::query()->findOrFail($payment->plan_id);
        $price = PlanPrice::query()->findOrFail($payment->plan_price_id);
        [$subscription, $kind, $startsAt, $endsAt] = $payment->isUpgrade()
            ? $this->applyUpgrade($plan, $price, $payment)
            : $this->applyToSubscription($tenant, $plan, $price, $payment, (string) ($providerPaymentId ?? $payment->transfer_reference));

        $now = CarbonImmutable::now();

        $payment->forceFill([
            'status' => PaymentStatus::Succeeded,
            'kind' => $kind,
            'subscription_id' => $subscription->getKey(),
            'provider_payment_id' => $providerPaymentId,
            'card_association' => $cardAssociation,
            'card_family' => $cardFamily,
            'card_last_four' => $cardLastFour,
            'period_starts_at' => $startsAt,
            'period_ends_at' => $endsAt,
            'failure_code' => null,
            'failure_message' => null,
            'failed_at' => null,
            'paid_at' => $now,
        ])->save();

        $payment->forceFill(['receipt_number' => sprintf('RST-%s-%06d', $now->format('Y'), $payment->getKey())])->save();

        $email = $payment->billing_details['email'] ?? null;

        if (is_string($email) && $email !== '') {
            $locale = $tenant->default_locale;
            $mail = new PaymentReceiptMail(
                receiptNumber: (string) $payment->receipt_number,
                businessName: $tenant->translate($locale)?->name ?? $tenant->slug,
                planName: $plan->translate($locale)?->name ?? $plan->code,
                interval: $payment->interval,
                periodCount: max(1, (int) $payment->period_count),
                amountMinor: $payment->amount,
                currency: $payment->currency,
                periodStartsAt: $startsAt,
                periodEndsAt: $endsAt,
                paidAt: $now,
                url: ViewReceipt::getUrl(['payment' => $payment->public_id], panel: 'app', tenant: $tenant),
            );

            DB::afterCommit(fn () => Mail::to($email)->locale($locale)->queue($mail));
        }
    }

    private function succeedShopOrder(Payment $payment, ?string $providerPaymentId, ?string $cardAssociation, ?string $cardFamily, ?string $cardLastFour): void
    {
        $now = CarbonImmutable::now();

        $payment->forceFill([
            'status' => PaymentStatus::Succeeded,
            'provider_payment_id' => $providerPaymentId,
            'card_association' => $cardAssociation,
            'card_family' => $cardFamily,
            'card_last_four' => $cardLastFour,
            'failure_code' => null,
            'failure_message' => null,
            'failed_at' => null,
            'paid_at' => $now,
            'receipt_number' => sprintf('RST-%s-%06d', $now->format('Y'), $payment->getKey()),
        ])->save();

        $this->shopOrders->markPaid($this->shopOrder($payment), $now);
    }

    private function shopOrder(Payment $payment): ShopOrder
    {
        return ShopOrder::query()->findOrFail($payment->shop_order_id);
    }

    /**
     * @return array{Subscription, PaymentKind, CarbonImmutable, CarbonImmutable}
     */
    private function applyToSubscription(Tenant $tenant, Plan $plan, PlanPrice $price, Payment $payment, string $reference): array
    {
        $now = CarbonImmutable::now();
        // A multi-year payment covers several periods of its interval at once.
        $months = $payment->interval->months() * max(1, (int) $payment->period_count);
        $live = Subscription::query()->whereNull('ended_at')->latest('id')->first();
        $provider = $payment->provider;

        if ($live === null) {
            $endsAt = $now->addMonthsNoOverflow($months);

            return [$this->subscriptions->resubscribe($tenant, $plan, $now, $endsAt, $price, $provider, $reference), PaymentKind::Resubscription, $now, $endsAt];
        }

        if ($live->status === SubscriptionStatus::Active && ! $live->isComplimentary()) {
            $startsAt = $live->current_period_ends_at !== null && $live->current_period_ends_at->isAfter($now) ? $live->current_period_ends_at : $now;
            $endsAt = $startsAt->addMonthsNoOverflow($months);
            $samePlan = (int) $live->plan_id === (int) $plan->getKey();

            if (! $samePlan) {
                // Started while no paid period ran, paid after another checkout activated a plan.
                Log::warning('Renewal paid for a different plan than the active one; the period was extended on the active plan.', [
                    'payment' => $payment->public_id,
                    'tenant_id' => $payment->tenant_id,
                ]);
            }

            return [$this->subscriptions->renew($live, $endsAt, $startsAt, $samePlan ? $price : null), PaymentKind::Renewal, $startsAt, $endsAt];
        }

        $this->subscriptions->changePlan($live, $plan);
        $startsAt = $live->status === SubscriptionStatus::Trialing && $live->trial_ends_at?->isAfter($now) ? $live->trial_ends_at : $now;
        $endsAt = $startsAt->addMonthsNoOverflow($months);

        return [$this->subscriptions->activate($live, $startsAt, $endsAt, $price, $provider, $reference), PaymentKind::Activation, $startsAt, $endsAt];
    }

    /**
     * The upgrade was priced for the running period when the checkout
     * started. If that period is still running, the plan changes now; if it
     * ended meanwhile (grace), the plan still changes but the next period
     * must be paid; anything else is logged for a manual review/refund.
     *
     * @return array{Subscription, PaymentKind, CarbonImmutable, CarbonImmutable}
     */
    private function applyUpgrade(Plan $plan, PlanPrice $price, Payment $payment): array
    {
        $live = Subscription::query()->whereNull('ended_at')->latest('id')->first();
        $startsAt = $payment->period_starts_at ?? CarbonImmutable::now();
        $endsAt = $payment->period_ends_at ?? CarbonImmutable::now();

        if ($live?->status === SubscriptionStatus::Active && $live->current_period_ends_at?->isFuture()) {
            return [$this->subscriptions->upgrade($live, $plan, $price), PaymentKind::Upgrade, $startsAt, $endsAt];
        }

        if ($live !== null && in_array($live->status, [SubscriptionStatus::Grace, SubscriptionStatus::PastDue], true)) {
            Log::warning('Upgrade paid after the period ended; the plan was changed, the next period is still due.', [
                'payment' => $payment->public_id,
                'tenant_id' => $payment->tenant_id,
            ]);

            return [$this->subscriptions->changePlan($live, $plan), PaymentKind::Upgrade, $startsAt, $endsAt];
        }

        Log::critical('Upgrade paid without a running subscription; not applied, review for a refund.', [
            'payment' => $payment->public_id,
            'tenant_id' => $payment->tenant_id,
        ]);

        return [$live ?? Subscription::query()->latest('id')->firstOrFail(), PaymentKind::Upgrade, $startsAt, $endsAt];
    }

    private function fail(Payment $payment, string $code, ?string $message): void
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_code' => mb_substr($code, 0, 64),
            'failure_message' => $message !== null ? mb_substr($message, 0, 255) : null,
            'failed_at' => CarbonImmutable::now(),
        ])->save();

        if ($payment->isShopOrder()) {
            $this->shopOrders->markPaymentFailed($this->shopOrder($payment));
        }
    }

    private function expireIfStale(Payment $payment): void
    {
        $staleBefore = CarbonImmutable::now()->subMinutes((int) config('billing.pending_expires_minutes'));

        if ($payment->status === PaymentStatus::Pending && $payment->created_at->lessThanOrEqualTo($staleBefore)) {
            $payment->forceFill(['status' => PaymentStatus::Expired])->save();

            if ($payment->isShopOrder()) {
                $this->shopOrders->markPaymentFailed($this->shopOrder($payment));
            }
        }
    }

    private function ensureCurrentTenant(Payment $payment): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $payment->tenant_id) {
            throw new CrossTenantWriteException('A payment can only be processed in its own TenantContext.');
        }
    }
}
