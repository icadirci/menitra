<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingReminderType: string
{
    case TrialEnding = 'trial_ending';
    case PeriodEnding = 'period_ending';
    case GraceStarted = 'grace_started';
}
