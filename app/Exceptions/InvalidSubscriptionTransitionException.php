<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\SubscriptionStatus;
use RuntimeException;

/**
 * Thrown by SubscriptionService when a transition isn't allowed from the
 * subscription's current state. Repeating an already applied transition is
 * not an error (transitions are idempotent).
 */
class InvalidSubscriptionTransitionException extends RuntimeException
{
    public static function from(?SubscriptionStatus $status): self
    {
        return new self(__('plans.errors.invalid_transition', ['status' => $status?->label() ?? '—']));
    }

    public static function liveSubscriptionExists(): self
    {
        return new self(__('plans.errors.live_subscription_exists'));
    }
}
