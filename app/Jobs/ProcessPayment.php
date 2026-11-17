<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Billing\PaymentProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks the provider for one payment's result and applies it (webhook).
 * The tenant comes from the dispatching context (TenantQueueListener); the
 * payment is read through the tenant scope, so another tenant's id finds
 * nothing. Idempotent (PaymentProcessor), retried with backoff when the
 * provider can't be reached.
 */
class ProcessPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 300, 900];

    public function __construct(public readonly int $paymentId) {}

    public function handle(PaymentProcessor $processor): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment !== null) {
            $processor->complete($payment);
        }
    }
}
