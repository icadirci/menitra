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

    /**
     * Distinct allergens of every item, in first-seen order (for theme
     * filters such as zen's "I avoid"). Derived from the snapshot only.
     *
     * @return list<LabelData>
     */
    public function allergens(): array
    {
        $allergens = [];

        foreach ($this->categories as $category) {
            foreach ($category->items as $item) {
                foreach ($item->allergens as $allergen) {
                    $allergens[$allergen->key] ??= $allergen;
                }
            }
        }

        return array_values($allergens);
    }
}
