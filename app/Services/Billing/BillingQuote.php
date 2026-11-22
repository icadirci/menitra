<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Support\Money;

/**
 * What $periods periods cost for a tenant: the plan price plus each branch
 * beyond plans.included_locations at plan_prices.extra_location_amount, both
 * multiplied by the number of periods (multi-year payments).
 */
final readonly class BillingQuote
{
    public function __construct(
        public Plan $plan,
        public PlanPrice $price,
        public int $locations,
        public int $extraLocations,
        public Money $base,
        public Money $extra,
        public Money $total,
        public int $periods = 1,
    ) {}
}
