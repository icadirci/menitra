<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Enums\BillingInterval;
use App\Enums\PaymentKind;
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
        $now = CarbonImmutable::now();

        if ($subscription === null
            || $subscription->status !== SubscriptionStatus::Active
            || $subscription->current_period_ends_at === null
            || ! $subscription->current_period_ends_at->isAfter($now)) {
            return new CheckoutEligibility(allowed: true, canChangePlan: true, subscription: $subscription);
        }

        $interval = $this->calculator->intervalOf($subscription);
        $opensAt = $subscription->current_period_ends_at->subDays((int) config('billing.renewal_window_days'));
        $renewalOpen = ! $opensAt->isAfter($now);

        return new CheckoutEligibility(
            allowed: $renewalOpen,
            canChangePlan: false,
            subscription: $subscription,
            reason: $renewalOpen ? null : 'renewal_not_open',
            opensAt: $renewalOpen ? null : $opensAt,
            canUpgrade: $this->upgradePlans($subscription, $interval) !== [],
            fixedInterval: $interval,
        );
    }

    /**
     * Plans the owner can pay for now: every selectable plan without a
     * running paid period; during one, the current plan (renewal window) and
     * the higher-priced plans (upgrade).
     *
     * @return list<Plan>
     */
    public function payablePlans(CheckoutEligibility $eligibility): array
    {
        $current = $eligibility->subscription?->plan;

        if ($eligibility->isActivePeriod()) {
            $plans = $this->upgradePlans($eligibility->subscription, $eligibility->fixedInterval);

            if ($eligibility->allowed && $current !== null && $current->is_active && $current->load(['translations', 'prices', 'limits'])->prices->contains(fn ($price): bool => $price->is_active)) {
                array_unshift($plans, $current);
            }

            return $plans;
        }

        $plans = $this->selectablePlans();

        if ($current !== null && $current->is_active && ! collect($plans)->contains(fn (Plan $plan): bool => $plan->is($current))) {
            array_unshift($plans, $current->load(['translations', 'prices', 'limits']));
        }

        return array_values(array_filter($plans, fn (Plan $plan): bool => $plan->prices->contains(fn ($price): bool => $price->is_active)));
    }

    /**
     * Whether paying for $plan now is a prorated upgrade of the running period.
     */
    public function isUpgrade(CheckoutEligibility $eligibility, Plan $plan): bool
    {
        return $eligibility->isActivePeriod() && ! $plan->is($eligibility->subscription?->plan);
    }

    /**
     * Selectable plans priced above the current plan in the period's interval.
     *
     * @return list<Plan>
     */
    private function upgradePlans(Subscription $subscription, BillingInterval $interval): array
    {
        $current = Plan::query()->with('prices')->find($subscription->plan_id);
        $currentPrice = $current?->priceFor($interval)?->amount ?? 0;

        return array_values(array_filter(
            $this->selectablePlans(),
            fn (Plan $plan): bool => ! $plan->is($current) && ($plan->priceFor($interval)?->amount ?? 0) > $currentPrice,
        ));
    }

    /**
     * @return list<Plan>
     */
    private function selectablePlans(): array
    {
        return Plan::query()->selectable()->with(['translations', 'prices', 'limits'])->orderBy('sort_order')->orderBy('id')->get()->all();
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

        if (! $eligibility->canPayAnything()) {
            throw CheckoutNotAllowedException::because($eligibility->reason ?? 'not_allowed', [
                'date' => $eligibility->opensAt?->setTimezone('Europe/Istanbul')->locale($locale)->isoFormat('LL'),
            ]);
        }

        if (! collect($this->payablePlans($eligibility))->contains(fn (Plan $payable): bool => $payable->is($plan))) {
            throw CheckoutNotAllowedException::because($eligibility->isActivePeriod() && ! $eligibility->allowed && $plan->is($eligibility->subscription?->plan)
                ? 'renewal_not_open'
                : 'plan_not_allowed', [
                    'date' => $eligibility->opensAt?->setTimezone('Europe/Istanbul')->locale($locale)->isoFormat('LL'),
                ]);
        }

        // An upgrade keeps the running period's interval; a renewal may switch it.
        $upgrade = $this->isUpgrade($eligibility, $plan) ? $this->calculator->upgradeQuote($eligibility->subscription, $plan) : null;
        $interval = $upgrade?->interval ?? $interval;

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
            'provider' => $this->gateway->name(),
            'billing_details' => $profile->snapshot(),
            ...($upgrade === null ? [
                'base_amount' => $quote->base->minor,
                'extra_locations' => $quote->extraLocations,
                'extra_location_amount' => $quote->extraLocations > 0 ? $quote->price->extra_location_amount : null,
                'amount' => $quote->total->minor,
            ] : [
                // The new plan for the remaining days (branches included) minus the credit.
                'kind' => PaymentKind::Upgrade,
                'base_amount' => $upgrade->charge->minor,
                'extra_locations' => 0,
                'credit_amount' => $upgrade->credit->minor,
                'prorated_days' => $upgrade->remainingDays,
                'amount' => $upgrade->total->minor,
                'period_starts_at' => CarbonImmutable::now(),
                'period_ends_at' => $upgrade->periodEndsAt,
            ]),
        ])->save();

        try {
            $session = $this->gateway->initializeCheckout($this->request($payment, $quote, $user, $ip, $locale, $upgrade));
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

    private function request(Payment $payment, BillingQuote $quote, User $user, string $ip, string $locale, ?UpgradeQuote $upgrade): CheckoutRequest
    {
        $planName = $quote->plan->translate($locale)?->name ?? $quote->plan->code;
        $interval = __("plans.intervals.{$payment->interval->value}", [], $locale);

        $lines = $upgrade !== null
            ? [new CheckoutLine('upgrade-'.$quote->plan->code, __('billing.checkout.upgrade_line', ['plan' => $planName, 'days' => $upgrade->remainingDays], $locale), $upgrade->total)]
            : [new CheckoutLine('plan-'.$quote->plan->code, "{$planName} ({$interval})", $quote->base)];

        if ($upgrade === null && ! $quote->extra->isZero()) {
            $lines[] = new CheckoutLine('extra-locations', __('billing.checkout.extra_locations_line', ['count' => $quote->extraLocations], $locale), $quote->extra);
        }

        return new CheckoutRequest(
            reference: $payment->public_id,
            amount: $payment->money(),
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
