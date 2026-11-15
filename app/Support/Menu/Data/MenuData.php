<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * The rendered menu.
 */
final readonly class MenuData
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public string $type,
        /** @var list<CategoryData> */ public array $categories,
        /** @var list<ItemData> */ public array $featured,
    ) {}
}
