<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\Location;
use App\Models\Plan;
use App\Support\Money;

/**
 * The only place a period's amount is computed. Branches are counted like
 * FeatureGate counts max_locations (every non-deleted branch of the tenant
 * in TenantContext).
 */
class BillingCalculator
{
    /**
     * @throws CheckoutNotAllowedException when the plan has no active price for the interval
     */
    public function quote(Plan $plan, BillingInterval $interval): BillingQuote
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
