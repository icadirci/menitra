<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Expired = 'expired';

    public function label(): string
    {
        return __("plans.statuses.{$this->value}");
    }

    /**
     * Filament badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::Trialing => 'info',
            self::Active => 'success',
            self::PastDue, self::Grace => 'warning',
            self::Expired => 'danger',
        };
    }
}
