<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Support\Money;

/**
 * The provider's verified view of a checkout. Only allow-listed, non-sensitive
 * fields (masked card data) leave the adapter.
 */
final readonly class CheckoutResult
{
    public function __construct(
        public CheckoutOutcome $outcome,
        public ?string $reference = null,
        public ?string $basketId = null,
        public ?string $paymentId = null,
        public ?Money $paidAmount = null,
        public ?string $cardAssociation = null,
        public ?string $cardFamily = null,
        public ?string $cardLastFour = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}
}
