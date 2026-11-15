<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * A size or portion with its own price.
 */
final readonly class VariantData
{
    public function __construct(
        public string $publicId,
        public string $name,
        public PriceData $price,
        public bool $isDefault,
        public bool $isAvailable,
    ) {}
}
