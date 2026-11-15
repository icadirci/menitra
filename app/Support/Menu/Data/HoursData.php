<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * An interval on an ISO weekday (1–7), HH:MM; end < start = past midnight.
 */
final readonly class HoursData
{
    public function __construct(
        public int $day,
        public string $opens,
        public string $closes,
    ) {}
}
