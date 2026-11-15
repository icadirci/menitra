<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Jobs\ProcessTenantSubscriptionLifecycle;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantAgnostic;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Hourly (routes/console.php). Finds live subscriptions with a due transition
 * (trial ended, period ended, grace ended) and dispatches one tenant-scoped
 * job per tenant (docs/architecture/subscriptions.md § Scheduled processing).
 *
 * TenantAgnostic: the scope bypass only finds tenant ids; the work itself runs
 * in each tenant's own context. Deleted and suspended tenants are skipped
 * (their menu is offline anyway); because grace is anchored to the missed
 * boundary, they catch up correctly once reactivated. A failing tenant is
 * reported and never stops the others. Re-running is safe: transitions are
 * idempotent.
 */
class ProcessSubscriptionLifecycle extends Command implements TenantAgnostic
{
    protected $signature = 'subscriptions:process-lifecycle';

    protected $description = 'Move due subscriptions to grace or expired (one job per tenant)';

    public function handle(TenantContext $context): int
    {
        $previous = $context->check() ? $context->tenant() : null;
        $dispatched = 0;
        $failed = 0;

        try {
            Tenant::query()
                ->where('status', TenantStatus::Active)
                ->whereIn('id', $this->dueTenantIds())
                ->orderBy('id')
                ->each(function (Tenant $tenant) use ($context, &$dispatched, &$failed): void {
                    try {
                        $context->set($tenant);
                        ProcessTenantSubscriptionLifecycle::dispatch();
                        $dispatched++;
                    } catch (Throwable $exception) {
                        // With the sync driver the job runs inline; isolate its failure.
                        $failed++;
                        report($exception);
                        $this->error("Tenant #{$tenant->getKey()}: {$exception->getMessage()}");
                    } finally {
                        $context->clear();
                    }
                });
        } finally {
            $previous !== null ? $context->set($previous) : $context->clear();
        }

        $this->info("Dispatched {$dispatched} tenant job(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Builder<Subscription>
     */
    private function dueTenantIds(): Builder
    {
        $now = now();

        return Subscription::withoutTenantScope()
            ->select('tenant_id')
            ->whereNull('ended_at')
            ->where(fn (Builder $due) => $due
                ->where(fn (Builder $q) => $q
                    ->where('status', SubscriptionStatus::Trialing)
                    ->where('trial_ends_at', '<=', $now))
                ->orWhere(fn (Builder $q) => $q
                    ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
                    ->where('current_period_ends_at', '<=', $now))
                ->orWhere(fn (Builder $q) => $q
                    ->where('status', SubscriptionStatus::Grace)
                    ->where('grace_ends_at', '<=', $now)));
    }
}
