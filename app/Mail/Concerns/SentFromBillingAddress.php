<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;

/**
 * Customer-facing payment mails (receipts, billing reminders, shop order
 * updates) are sent from the billing sender (`mail.billing_from`, e.g.
 * sales@menitra.com). Without one configured they use the global "from".
 */
trait SentFromBillingAddress
{
    protected function billingFrom(): Address
    {
        $address = config('mail.billing_from.address') ?: config('mail.from.address');
        $name = config('mail.billing_from.name') ?: config('mail.from.name');

        return new Address((string) $address, $name !== null ? (string) $name : null);
    }
}
