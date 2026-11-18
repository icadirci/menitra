<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which SubscriptionService transition a successful payment applied.
 */
enum PaymentKind: string
{
    // Trial, grace or past due → active (activate()).
    case Activation = 'activation';
    // A running paid period extended (renew()).
    case Renewal = 'renewal';
    // No live subscription → a new active row (resubscribe()).
    case Resubscription = 'resubscription';
    // A higher plan during a running paid period, effective at once; the
    // remaining days are prorated (upgrade()). Set when the checkout starts.
    case Upgrade = 'upgrade';

    public function label(): string
    {
        return __("billing.payment_kinds.{$this->value}");
    }
}
