<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Support\Money;

/**
 * Display data of one public plan, already resolved for the current locale.
 */
final readonly class PricingPlan
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public bool $featured,
        public Money $monthly,
        public ?Money $yearly,
        public ?Money $yearlyPerMonth,
        public ?int $yearlySavingPercent,
    ) {}
}
