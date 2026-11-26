<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Support\Money;

final readonly class CheckoutLine
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $amount,
        public bool $physical = false,
    ) {}
}
