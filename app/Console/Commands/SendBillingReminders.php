<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Jobs\SendTenantBillingReminders;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantAgnostic;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Daily (routes/console.php). Finds active tenants whose trial or paid period
 * ends within the largest reminder threshold, or who are in grace, and
 * dispatches one tenant-scoped SendTenantBillingReminders job per tenant.
 * TenantAgnostic: the scope bypass only finds tenant ids. Idempotent.
 */
class SendBillingReminders extends Command implements TenantAgnostic
{
    protected $signature = 'billing:send-reminders';

    protected $description = 'Mail payment reminders to owners (one job per tenant)';

    public function handle(TenantContext $context): int
    {
        $previous = $context->check() ? $context->tenant() : null;
        $horizon = now()->addDays((int) collect(config('billing.reminder_days'))->max());
        $dispatched = 0;
        $failed = 0;

        $tenantIds = Subscription::withoutTenantScope()
            ->select('tenant_id')
            ->whereNull('ended_at')
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where('status', SubscriptionStatus::Trialing)->whereBetween('trial_ends_at', [now(), $horizon]))
                ->orWhere(fn (Builder $q) => $q->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])->whereBetween('current_period_ends_at', [now(), $horizon]))
                ->orWhere(fn (Builder $q) => $q->where('status', SubscriptionStatus::Grace)->where('grace_ends_at', '>', now())));

        try {
            Tenant::query()->where('status', TenantStatus::Active)->whereIn('id', $tenantIds)->orderBy('id')->each(function (Tenant $tenant) use ($context, &$dispatched, &$failed): void {
                try {
                    $context->set($tenant);
                    SendTenantBillingReminders::dispatch();
                    $dispatched++;
                } catch (Throwable $exception) {
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
}
