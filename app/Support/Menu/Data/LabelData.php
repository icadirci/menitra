<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * A translated enum label (allergen, dietary label).
 */
final readonly class LabelData
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
