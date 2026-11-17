<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Whether the tenant can pay now, and for which plans.
 *
 * - No live subscription, trialing, grace or past due: pay now, any selectable
 *   plan (no paid period is running, so a plan change needs no proration).
 * - Active: only the current plan, and only within the renewal window before
 *   the period ends (billing.renewal_window_days).
 */
final readonly class CheckoutEligibility
{
    public function __construct(
        public bool $allowed,
        public bool $canChangePlan,
        public ?Subscription $subscription,
        public ?string $reason = null,
        public ?CarbonImmutable $opensAt = null,
    ) {}
}
