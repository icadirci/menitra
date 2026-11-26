<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Enums\BillingInterval;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PaymentGatewayException;
use App\Filament\App\Pages\BankTransferPayment;
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
 * pending payment and opens the provider's hosted checkout, or (bank
 * transfer) shows the account to pay into. Nothing here
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
        private readonly PaymentMethods $methods,
        private readonly BankTransferService $bankTransfers,
    ) {}

    public function eligibility(): CheckoutEligibility
    {
        $subscription = Subscription::query()->with('plan')->whereNull('ended_at')->latest('id')->first();
        $now = CarbonImmutable::now();

        // A complimentary period was not paid: any plan can be bought as a new period.
        if ($subscription === null
            || $subscription->isComplimentary()
            || $subscription->status !== SubscriptionStatus::Active
            || $subscription->current_period_ends_at === null
            || ! $subscription->current_period_ends_at->isAfter($now)) {
            return new CheckoutEligibility(allowed: true, canChangePlan: true, subscription: $subscription);
        }

        $interval = $this->calculator->intervalOf($subscription);
        // An extension starts at the current end; one more month must still fit under the limit.
        $extendable = ! $subscription->current_period_ends_at->isAfter($this->prepaidLimit($now)->subMonthNoOverflow());

        return new CheckoutEligibility(
            allowed: $extendable,
            canChangePlan: false,
            subscription: $subscription,
            reason: $extendable ? null : 'prepaid_limit',
            opensAt: $extendable ? null : $subscription->current_period_ends_at->subYears($this->maxPrepaidYears()),
            canUpgrade: $this->upgradePlans($subscription, $interval) !== [],
            fixedInterval: $interval,
        );
    }

    /**
     * Where a new paid period would start if paid now (the processor decides
     * again when the money arrives): the end of the running paid period, the
     * end of a running trial, else now.
     */
    public function nextPeriodStart(CheckoutEligibility $eligibility, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();
        $subscription = $eligibility->subscription;

        return match (true) {
            $eligibility->isActivePeriod() && $subscription?->current_period_ends_at?->isAfter($now) => $subscription->current_period_ends_at,
            $subscription?->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at?->isAfter($now) === true => $subscription->trial_ends_at,
            default => $now,
        };
    }

    /**
     * How many periods of $interval one payment may cover now: monthly at
     * most one, yearly up to billing.max_years_per_payment; never past the
     * prepaid limit (billing.max_prepaid_years from today plus one month of
     * slack for a running trial or month). 0 = this interval can't be paid now.
     */
    public function maxPeriods(CheckoutEligibility $eligibility, BillingInterval $interval, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $startsAt = $this->nextPeriodStart($eligibility, $now);
        $limit = $this->prepaidLimit($now);
        $max = $interval === BillingInterval::Yearly ? max(1, (int) config('billing.max_years_per_payment')) : 1;

        for ($periods = $max; $periods >= 1; $periods--) {
            if (! $startsAt->addMonthsNoOverflow($interval->months() * $periods)->isAfter($limit)) {
                return $periods;
            }
        }

        return 0;
    }

    /**
     * The latest moment a paid period may reach.
     */
    private function prepaidLimit(CarbonImmutable $now): CarbonImmutable
    {
        return $now->addYears($this->maxPrepaidYears())->addMonthNoOverflow();
    }

    private function maxPrepaidYears(): int
    {
        return max(1, (int) config('billing.max_prepaid_years'));
    }

    /**
     * Plans the owner can pay for now: every selectable plan without a
     * running paid period; during one, the current plan (extension) and the
     * higher-priced plans (upgrade).
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
     * @param  int  $periods  periods of $interval to pay for at once (yearly: up to maxPeriods(); monthly and upgrades: 1)
     * @return string the provider's payment page URL, or the bank transfer page
     *
     * @throws AuthorizationException|CheckoutNotAllowedException
     */
    public function start(Tenant $tenant, User $user, Plan $plan, BillingInterval $interval, string $ip, string $locale, int $periods = 1, PaymentMethod $method = PaymentMethod::Card): string
    {
        $this->ensureCurrentTenant($tenant);

        if (! $user->can('manageSettings', $tenant)) {
            throw new AuthorizationException;
        }

        $this->methods->ensureEnabled($method);
        $this->bankTransfers->ensureNoneOpen(shop: false);

        $eligibility = $this->eligibility();

        if (! $eligibility->canPayAnything()) {
            throw CheckoutNotAllowedException::because($eligibility->reason ?? 'not_allowed', [
                'date' => $eligibility->opensAt?->setTimezone('Europe/Istanbul')->locale($locale)->isoFormat('LL'),
            ]);
        }

        if (! collect($this->payablePlans($eligibility))->contains(fn (Plan $payable): bool => $payable->is($plan))) {
            throw CheckoutNotAllowedException::because($eligibility->isActivePeriod() && ! $eligibility->allowed && $plan->is($eligibility->subscription?->plan)
                ? 'prepaid_limit'
                : 'plan_not_allowed', [
                    'date' => $eligibility->opensAt?->setTimezone('Europe/Istanbul')->locale($locale)->isoFormat('LL'),
                ]);
        }

        // An upgrade keeps the running period's interval and covers the remaining time; a renewal may switch it.
        $upgrade = $this->isUpgrade($eligibility, $plan) ? $this->calculator->upgradeQuote($eligibility->subscription, $plan) : null;
        $interval = $upgrade?->interval ?? $interval;

        if ($upgrade !== null) {
            $periods = 1;
        } elseif ($periods < 1 || $periods > $this->maxPeriods($eligibility, $interval)) {
            throw CheckoutNotAllowedException::because('periods_not_allowed', [
                'max' => max(1, $this->maxPeriods($eligibility, $interval)),
            ]);
        }

        $profile = $this->profiles->current() ?? throw CheckoutNotAllowedException::because('billing_profile_missing');

        if (Payment::query()->where('created_at', '>=', now()->subHour())->count() >= self::MAX_ATTEMPTS_PER_HOUR) {
            throw CheckoutNotAllowedException::because('too_many_attempts');
        }

        $quote = $this->calculator->quote($plan, $interval, $periods);
        $bankTransfer = $method === PaymentMethod::BankTransfer;

        $payment = new Payment;
        $payment->forceFill([
            'plan_id' => $plan->getKey(),
            'plan_price_id' => $quote->price->getKey(),
            'user_id' => $user->getKey(),
            'interval' => $interval,
            'period_count' => $periods,
            'status' => PaymentStatus::Pending,
            'currency' => $quote->total->currency,
            'provider' => $bankTransfer ? PaymentMethod::BANK_TRANSFER_PROVIDER : $this->gateway->name(),
            'transfer_reference' => $bankTransfer ? $this->bankTransfers->newReference() : null,
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

        // The owner transfers the amount; a super admin applies it once the money arrived.
        if ($bankTransfer) {
            return BankTransferPayment::getUrl(['payment' => $payment->public_id], panel: 'app', tenant: $tenant);
        }

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

            // A provider error code means iyzico answered and refused the request (e.g. a
            // billing field it doesn't accept); without one it couldn't be reached.
            throw CheckoutNotAllowedException::because($exception->providerCode !== null ? 'gateway_rejected' : 'gateway_unavailable');
        }

        $payment->forceFill(['provider_token' => $session->token])->save();

        return $session->paymentPageUrl;
    }

    private function request(Payment $payment, BillingQuote $quote, User $user, string $ip, string $locale, ?UpgradeQuote $upgrade): CheckoutRequest
    {
        $planName = $quote->plan->translate($locale)?->name ?? $quote->plan->code;
        $interval = $payment->interval->periodLabel($payment->period_count, $locale);

        $lines = $upgrade !== null
            ? [new CheckoutLine('upgrade-'.$quote->plan->code, __('billing.checkout.upgrade_line', ['plan' => $planName, 'days' => $upgrade->remainingDays], $locale), $upgrade->total)]
            : [new CheckoutLine('plan-'.$quote->plan->code, "{$planName} ({$interval})", $quote->base)];

        if ($upgrade === null && ! $quote->extra->isZero()) {
            $lines[] = new CheckoutLine('extra-locations', $quote->periods > 1
                ? __('billing.checkout.extra_locations_line_periods', ['count' => $quote->extraLocations, 'period' => $interval], $locale)
                : __('billing.checkout.extra_locations_line', ['count' => $quote->extraLocations], $locale), $quote->extra);
        }

        return new CheckoutRequest(
            reference: $payment->public_id,
            amount: $payment->money(),
            lines: $lines,
            buyerId: 'user-'.$user->getKey(),
            buyerName: $user->name,
            // The billing contact, as on the receipt; the account email only when none is set.
            buyerEmail: is_string($payment->billing_details['email'] ?? null) && $payment->billing_details['email'] !== '' ? $payment->billing_details['email'] : $user->email,
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
