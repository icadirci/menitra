<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\SubscriptionStatus;

/**
 * Subscription details on /account (owners only). Dates are pre-formatted
 * for display (Europe/Istanbul, current locale).
 *
 * $status is null when the tenant never had a subscription. $keyDateField
 * names the next key date (trial_ends_at, current_period_ends_at or
 * grace_ends_at) for the label; null when there is none (expired).
 */
final readonly class AccountSubscription
{
    public function __construct(
        public ?SubscriptionStatus $status,
        public ?string $planName,
        public ?string $keyDateField,
        public ?string $keyDate,
        public ?int $keyDateDaysLeft,
        public ?string $accessUntil,
        public ?string $endedAt,
        public bool $cancelAtPeriodEnd,
    ) {}

    public function exists(): bool
    {
        return $this->status !== null;
    }

    public function isGrace(): bool
    {
        return $this->status === SubscriptionStatus::Grace;
    }

    public function isPastDue(): bool
    {
        return $this->status === SubscriptionStatus::PastDue;
    }
}
