<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\TenantStatus;
use App\Jobs\ReconcileTenantPayments;
use App\Models\Payment;
use App\Models\Tenant;
use App\Tenancy\TenantAgnostic;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Every ten minutes (routes/console.php). Finds tenants with open checkouts
 * older than billing.reconcile_after_minutes and dispatches one tenant-scoped
 * job per tenant. TenantAgnostic: the scope bypass only finds tenant ids.
 * Only active tenants: TenantQueueListener refuses jobs of suspended or
 * deleted ones. Their open checkouts stay pending until the tenant is
 * reactivated (the browser callback, which runs synchronously, still records
 * a payment made just before a suspension).
 */
class ReconcilePayments extends Command implements TenantAgnostic
{
    protected $signature = 'billing:reconcile-payments';

    protected $description = 'Apply or expire open payment checkouts (one job per tenant)';

    public function handle(TenantContext $context): int
    {
        $previous = $context->check() ? $context->tenant() : null;
        $dispatched = 0;
        $failed = 0;

        $tenantIds = Payment::withoutTenantScope()
            ->select('tenant_id')
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('provider_token')
            ->where('created_at', '<=', now()->subMinutes((int) config('billing.reconcile_after_minutes')));

        try {
            Tenant::query()->where('status', TenantStatus::Active)->whereIn('id', $tenantIds)->orderBy('id')->each(function (Tenant $tenant) use ($context, &$dispatched, &$failed): void {
                try {
                    $context->set($tenant);
                    ReconcileTenantPayments::dispatch();
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
