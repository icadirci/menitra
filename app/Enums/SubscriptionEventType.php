<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionEventType: string
{
    case TrialStarted = 'trial_started';
    case Activated = 'activated';
    case Renewed = 'renewed';
    case PaymentFailed = 'payment_failed';
    case CancelRequested = 'cancel_requested';
    case CancelReverted = 'cancel_reverted';
    case GraceStarted = 'grace_started';
    case Expired = 'expired';
    case PlanChanged = 'plan_changed';
    case Reactivated = 'reactivated';
}
