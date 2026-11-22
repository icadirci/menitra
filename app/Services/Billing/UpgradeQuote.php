<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * A mid-period upgrade: the new plan's price for all the remaining prepaid
 * time (several stacked or multi-year periods included) minus the unused
 * value of what was paid for it, both at the rate of one period ("unit").
 *
 *   charge = full × remaining / unit      credit = paid per unit × remaining / unit
 *   total  = max(charge − credit, minimum)
 */
final readonly class UpgradeQuote
{
    public function __construct(
        public BillingQuote $full,
        public BillingInterval $interval,
        public CarbonImmutable $periodEndsAt,
        public int $remainingDays,
        public Money $charge,
        public Money $credit,
        public Money $total,
    ) {}
}
