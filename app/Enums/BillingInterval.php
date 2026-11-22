<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __("plans.intervals.{$this->value}");
    }

    /**
     * "Yearly" for one period, "3 years" for several (multi-year payments).
     */
    public function periodLabel(int $count = 1, ?string $locale = null): string
    {
        return $count > 1
            ? trans_choice("plans.interval_counts.{$this->value}", $count, ['count' => $count], $locale)
            : __("plans.intervals.{$this->value}", [], $locale);
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }
}
