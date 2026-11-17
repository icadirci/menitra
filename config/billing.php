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

    // An active subscription can be paid (extended) only this many days
    // before its period ends, so a period can't be prepaid indefinitely.
    'renewal_window_days' => 30,

    // A checkout that is still unpaid after this many minutes is marked
    // expired by the reconciliation command (the iyzico token is no longer usable).
    'pending_expires_minutes' => 120,

    // Reconciliation leaves fresh checkouts alone (the customer may still be paying).
    'reconcile_after_minutes' => 5,

    // Payment reminder mails to owners, in days before the trial or period ends.
    'reminder_days' => [7, 1],

];
