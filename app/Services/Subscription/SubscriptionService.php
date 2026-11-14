<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The only place subscription state changes. Every transition runs in a
 * transaction and is recorded in subscription_events. Further transitions
 * (activate, grace, expire, cancel …) are added with the lifecycle phase.
 */
class SubscriptionService
{
    public function startTrial(Tenant $tenant, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan): Subscription {
            $now = CarbonImmutable::now();

            $subscription = new Subscription;
            $subscription->forceFill([
                'tenant_id' => $tenant->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $now->addDays($plan->trialDays()),
            ])->save();

            $this->record($subscription, SubscriptionEventType::TrialStarted, null, SubscriptionStatus::Trialing, $now, [
                'plan' => $plan->code,
                'trial_days' => $plan->trialDays(),
            ]);

            return $subscription;
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function record(
        Subscription $subscription,
        SubscriptionEventType $type,
        ?SubscriptionStatus $from,
        ?SubscriptionStatus $to,
        CarbonImmutable $at,
        array $meta = [],
    ): void {
        (new SubscriptionEvent)->forceFill([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'meta' => $meta,
            'occurred_at' => $at,
        ])->save();
    }
}
