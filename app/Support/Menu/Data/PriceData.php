<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * A price in minor units with its pre-formatted display string (e.g. ₺85,00).
 */
final readonly class PriceData
{
    public function __construct(
        public int $minor,
        public string $formatted,
    ) {}
}
