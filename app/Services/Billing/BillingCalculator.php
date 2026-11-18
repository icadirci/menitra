<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The only place a period's amount is computed. Branches are counted like
 * FeatureGate counts max_locations (every non-deleted branch of the tenant
 * in TenantContext).
 */
class BillingCalculator
{
    /** iyzico doesn't take tiny amounts; an upgrade costs at least this (kuruş). */
    public const int MINIMUM_UPGRADE_AMOUNT = 100;

    /** @var array<string, BillingQuote> */
    private array $quotes = [];

    /**
     * @throws CheckoutNotAllowedException when the plan has no active price for the interval
     */
    public function quote(Plan $plan, BillingInterval $interval): BillingQuote
    {
        return $this->quotes[$plan->getKey().'|'.$interval->value] ??= $this->computeQuote($plan, $interval);
    }

    /**
     * The billing interval of a running paid period: its price's interval,
     * else guessed from the period length.
     */
    public function intervalOf(Subscription $subscription): BillingInterval
    {
        $price = $subscription->plan_price_id !== null ? PlanPrice::query()->find($subscription->plan_price_id) : null;

        if ($price !== null) {
            return $price->interval;
        }

        $start = $subscription->current_period_starts_at;
        $end = $subscription->current_period_ends_at;

        return $start !== null && $end !== null && $start->diffInDays($end) > 62 ? BillingInterval::Yearly : BillingInterval::Monthly;
    }

    /**
     * Upgrading the active subscription to $plan for the rest of its period.
     *
     * @throws CheckoutNotAllowedException when $plan has no price in the period's interval
     */
    public function upgradeQuote(Subscription $subscription, Plan $plan, ?CarbonImmutable $now = null): UpgradeQuote
    {
        $now ??= CarbonImmutable::now();
        $interval = $this->intervalOf($subscription);
        $full = $this->quote($plan, $interval);
        $endsAt = $subscription->current_period_ends_at ?? throw CheckoutNotAllowedException::because('not_allowed');
        $startsAt = $subscription->current_period_starts_at ?? $endsAt->subMonthsNoOverflow($interval->months());

        $period = max(1, $startsAt->diffInSeconds($endsAt, true));
        $remaining = (int) min($period, max(0, $now->diffInSeconds($endsAt, false)));

        $charge = intdiv($full->total->minor * $remaining, (int) $period);
        $credit = intdiv($this->paidForPeriod($subscription, $interval) * $remaining, (int) $period);
        $currency = $full->total->currency;

        return new UpgradeQuote(
            full: $full,
            interval: $interval,
            periodEndsAt: $endsAt,
            remainingDays: (int) ceil($remaining / 86400),
            charge: Money::fromMinor($charge, $currency),
            credit: Money::fromMinor($credit, $currency),
            total: Money::fromMinor(max(self::MINIMUM_UPGRADE_AMOUNT, $charge - $credit), $currency),
        );
    }

    /**
     * The value of the running period on the current plan: what was actually
     * paid for it on this plan (extra branches included), else the plan's
     * list price (after an earlier upgrade, or a period not paid in the app).
     */
    private function paidForPeriod(Subscription $subscription, BillingInterval $interval): int
    {
        $payment = Payment::query()
            ->where('subscription_id', $subscription->getKey())
            ->where('status', PaymentStatus::Succeeded)
            ->where('plan_id', $subscription->plan_id)
            ->where('period_ends_at', $subscription->current_period_ends_at)
            ->latest('id')
            ->first();

        if ($payment !== null && ! $payment->isUpgrade()) {
            return $payment->amount;
        }

        $plan = Plan::query()->with('prices')->find($subscription->plan_id);

        return $plan?->priceFor($interval) !== null ? $this->quote($plan, $interval)->total->minor : 0;
    }

    private function computeQuote(Plan $plan, BillingInterval $interval): BillingQuote
    {
        $plan->loadMissing('prices');
        $price = $plan->priceFor($interval) ?? throw CheckoutNotAllowedException::because('no_price');

        $locations = Location::query()->count();
        $extraLocations = $plan->included_locations !== null && $price->extra_location_amount !== null
            ? max(0, $locations - $plan->included_locations)
            : 0;

        $extra = $extraLocations * (int) $price->extra_location_amount;

        return new BillingQuote(
            plan: $plan,
            price: $price,
            locations: $locations,
            extraLocations: $extraLocations,
            base: Money::fromMinor($price->amount, $price->currency),
            extra: Money::fromMinor($extra, $price->currency),
            total: Money::fromMinor($price->amount + $extra, $price->currency),
        );
    }
}
