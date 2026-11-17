<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Billing\PaymentProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Settles one tenant's open checkouts (billing:reconcile-payments): paid
 * ones are applied even when the browser never came back and no webhook
 * arrived, abandoned ones expire. Tenant from the dispatching context; a
 * failing payment is reported and never blocks the others.
 */
class ReconcileTenantPayments implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(PaymentProcessor $processor): void
    {
        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('provider_token')
            ->where('created_at', '<=', now()->subMinutes((int) config('billing.reconcile_after_minutes')))
            ->orderBy('id')
            ->each(function (Payment $payment) use ($processor): void {
                try {
                    $processor->complete($payment);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }
}
