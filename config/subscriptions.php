<?php

declare(strict_types=1);

return [

    // Plan code used for registration trials (seeded by PlanSeeder; placeholder until plans are defined).
    'default_plan' => env('SUBSCRIPTIONS_DEFAULT_PLAN', 'starter'),

    // Fallback when the plan doesn't define trial_days. Working default pending confirmation.
    'trial_days' => 14,

    // Grace period length. Working default pending confirmation.
    'grace_days' => 7,

    'currency' => 'TRY',

];
