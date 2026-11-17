<?php

declare(strict_types=1);

namespace App\Support\Billing;

enum CheckoutOutcome: string
{
    // Paid and approved by fraud control.
    case Succeeded = 'succeeded';
    // Declined, rejected by fraud control or cancelled.
    case Failed = 'failed';
    // Not paid yet (customer still on the page) or in fraud review.
    case Pending = 'pending';
}
