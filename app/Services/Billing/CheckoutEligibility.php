<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * What the tenant can pay for now (CheckoutService::eligibility()).
 *
 * - No live subscription, trialing, grace or past due: any selectable plan,
 *   monthly or yearly (no paid period runs, nothing to prorate).
 * - Active: the current plan only within the renewal window before the
 *   period ends ($allowed); a higher-priced plan at any time as an
 *   immediate, prorated upgrade ($canUpgrade) in the period's interval
 *   ($fixedInterval).
 */
final readonly class CheckoutEligibility
{
    public function __construct(
        public bool $allowed,
        public bool $canChangePlan,
        public ?Subscription $subscription,
        public ?string $reason = null,
        public ?CarbonImmutable $opensAt = null,
        public bool $canUpgrade = false,
        public ?BillingInterval $fixedInterval = null,
    ) {}

    public function isActivePeriod(): bool
    {
        return $this->fixedInterval !== null;
    }

    public function canPayAnything(): bool
    {
        return $this->allowed || $this->canUpgrade;
    }
}
