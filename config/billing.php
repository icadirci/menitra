<?php

declare(strict_types=1);

/*
| Manual period payments through iyzico Checkout Form
| (docs/architecture/subscriptions.md § Payments). Credentials live in
| config/services.php (iyzico).
*/

return [

    // Payment provider adapter bound to App\Contracts\PaymentGateway.
    'gateway' => 'iyzico',

    // A yearly payment covers 1 to this many years at once.
    'max_years_per_payment' => 5,

    // A running subscription can be extended at any time, but a paid period
    // may reach at most this many years (plus one month of slack for the
    // running trial or month) beyond today.
    'max_prepaid_years' => 5,

    // A checkout that is still unpaid after this many minutes is marked
    // expired by the reconciliation command (the iyzico token is no longer usable).
    'pending_expires_minutes' => 120,

    // Reconciliation leaves fresh checkouts alone (the customer may still be paying).
    'reconcile_after_minutes' => 5,

    // Payment reminder mails to owners, in days before the trial or period ends.
    'reminder_days' => [7, 1],

];
