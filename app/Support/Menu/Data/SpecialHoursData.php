<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * A date-specific exception (Y-m-d); closed all day or one interval.
 */
final readonly class SpecialHoursData
{
    public function __construct(
        public string $date,
        public bool $isClosed,
        public ?string $opens,
        public ?string $closes,
    ) {}
}
