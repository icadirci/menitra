<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * One option of an extra group; price delta is signed (null = free).
 */
final readonly class ExtraData
{
    public function __construct(
        public string $publicId,
        public string $name,
        public ?PriceData $priceDelta,
        public bool $isAvailable,
    ) {}
}
