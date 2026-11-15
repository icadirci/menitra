<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * A visible category with its visible items.
 */
final readonly class CategoryData
{
    public function __construct(
        public int $id,
        public string $anchor,
        public string $name,
        public ?string $description,
        public ?string $imageUrl,
        /** @var list<ItemData> */ public array $items,
    ) {}
}
