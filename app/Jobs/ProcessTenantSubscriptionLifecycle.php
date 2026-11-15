<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies the due subscription transitions of one tenant. It carries no
 * tenant id: the tenant comes from the dispatching context through
 * TenantQueueListener, so a missing, deleted or suspended tenant fails the job
 * before handle() runs, and without a context the scoped query throws.
 * Idempotent (SubscriptionService), so retries and duplicate dispatches are safe.
 */
class ProcessTenantSubscriptionLifecycle implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(SubscriptionService $subscriptions): void
    {
        $subscription = Subscription::query()->whereNull('ended_at')->first();

        if ($subscription !== null) {
            $subscriptions->processDue($subscription);
        }
    }
}
