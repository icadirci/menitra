<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\CheckoutLine;
use App\Support\Billing\CheckoutRequest;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

/**
 * Starts a manual period payment (docs/architecture/subscriptions.md § Payments):
 * checks who may pay and what, computes the amount on the server, stores a
 * pending payment and opens the provider's hosted checkout. Nothing here
 * changes the subscription; PaymentProcessor applies the verified result.
 */
class CheckoutService
{
    /** Pending checkouts a tenant may open per hour (abandoned pages included). */
    private const int MAX_ATTEMPTS_PER_HOUR = 10;

    public function __construct(
        private readonly TenantContext $context,
        private readonly PaymentGateway $gateway,
        private readonly BillingCalculator $calculator,
        private readonly BillingProfileService $profiles,
    ) {}

    public function eligibility(): CheckoutEligibility
    {
        $subscription = Subscription::query()->with('plan')->whereNull('ended_at')->latest('id')->first();

        if ($subscription === null || $subscription->status !== SubscriptionStatus::Active) {
            return new CheckoutEligibility(allowed: true, canChangePlan: true, subscription: $subscription);
        }

        $endsAt = $subscription->current_period_ends_at;
        $opensAt = $endsAt?->subDays((int) config('billing.renewal_window_days'));

        if ($endsAt !== null && $opensAt->isFuture()) {
            return new CheckoutEligibility(allowed: false, canChangePlan: false, subscription: $subscription, reason: 'renewal_not_open', opensAt: $opensAt);
        }

        return new CheckoutEligibility(allowed: true, canChangePlan: false, subscription: $subscription);
    }

    /**
     * Plans the owner can pay for now: the current plan, plus every selectable
     * plan when a plan change is allowed.
     *
     * @return list<Plan>
     */
    public function payablePlans(CheckoutEligibility $eligibility): array
    {
        $current = $eligibility->subscription?->plan;

        $plans = $eligibility->canChangePlan
            ? Plan::query()->selectable()->with(['translations', 'prices'])->orderBy('sort_order')->get()->all()
            : [];

        if ($current !== null && $current->is_active && ! collect($plans)->contains(fn (Plan $plan): bool => $plan->is($current))) {
            array_unshift($plans, $current->load(['translations', 'prices']));
        }

        return array_values(array_filter($plans, fn (Plan $plan): bool => $plan->prices->contains(fn ($price): bool => $price->is_active)));
    }

    /**
     * @return string the provider's payment page URL
     *
     * @throws AuthorizationException|CheckoutNotAllowedException
     */
    public function start(Tenant $tenant, User $user, Plan $plan, BillingInterval $interval, string $ip, string $locale): string
    {
        $this->ensureCurrentTenant($tenant);

        if (! $user->can('manageSettings', $tenant)) {
            throw new AuthorizationException;
        }

        $eligibility = $this->eligibility();

        if (! $eligibility->allowed) {
            throw CheckoutNotAllowedException::because($eligibility->reason ?? 'not_allowed', [
                'date' => $eligibility->opensAt?->setTimezone('Europe/Istanbul')->locale($locale)->isoFormat('LL'),
            ]);
        }

        if (! collect($this->payablePlans($eligibility))->contains(fn (Plan $payable): bool => $payable->is($plan))) {
            throw CheckoutNotAllowedException::because('plan_not_allowed');
        }

        $profile = $this->profiles->current() ?? throw CheckoutNotAllowedException::because('billing_profile_missing');

        if (Payment::query()->where('created_at', '>=', now()->subHour())->count() >= self::MAX_ATTEMPTS_PER_HOUR) {
            throw CheckoutNotAllowedException::because('too_many_attempts');
        }

        $quote = $this->calculator->quote($plan, $interval);

        $payment = new Payment;
        $payment->forceFill([
            'plan_id' => $plan->getKey(),
            'plan_price_id' => $quote->price->getKey(),
            'user_id' => $user->getKey(),
            'interval' => $interval,
            'status' => PaymentStatus::Pending,
            'currency' => $quote->total->currency,
            'base_amount' => $quote->base->minor,
            'extra_locations' => $quote->extraLocations,
            'extra_location_amount' => $quote->extraLocations > 0 ? $quote->price->extra_location_amount : null,
            'amount' => $quote->total->minor,
            'provider' => $this->gateway->name(),
            'billing_details' => $profile->snapshot(),
        ])->save();

        try {
            $session = $this->gateway->initializeCheckout($this->request($payment, $quote, $user, $ip, $locale));
        } catch (PaymentGatewayException $exception) {
            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_code' => $exception->providerCode !== null ? mb_substr('initialize:'.$exception->providerCode, 0, 64) : 'initialize_failed',
                'failed_at' => CarbonImmutable::now(),
            ])->save();

            Log::warning('Payment checkout could not be started.', ['payment' => $payment->public_id, 'error' => $exception->getMessage()]);
            report($exception);

            throw CheckoutNotAllowedException::because('gateway_unavailable');
        }

        $payment->forceFill(['provider_token' => $session->token])->save();

        return $session->paymentPageUrl;
    }

    private function request(Payment $payment, BillingQuote $quote, User $user, string $ip, string $locale): CheckoutRequest
    {
        $planName = $quote->plan->translate($locale)?->name ?? $quote->plan->code;
        $interval = __("plans.intervals.{$payment->interval->value}", [], $locale);

        $lines = [new CheckoutLine('plan-'.$quote->plan->code, "{$planName} ({$interval})", $quote->base)];

        if (! $quote->extra->isZero()) {
            $lines[] = new CheckoutLine('extra-locations', __('billing.checkout.extra_locations_line', ['count' => $quote->extraLocations], $locale), $quote->extra);
        }

        return new CheckoutRequest(
            reference: $payment->public_id,
            amount: $quote->total,
            lines: $lines,
            buyerId: 'user-'.$user->getKey(),
            buyerName: $user->name,
            buyerEmail: $user->email,
            billing: $payment->billing_details,
            buyerIp: $ip,
            callbackUrl: route('billing.iyzico.callback'),
            locale: $locale,
            buyerRegisteredAt: $user->created_at,
        );
    }

    private function ensureCurrentTenant(Tenant $tenant): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('A checkout can only be started for the tenant in the current TenantContext.');
        }
    }
}
