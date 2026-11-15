<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Dietary labels of an item (menu_item_dietary_labels.label). Extendable.
 */
enum DietaryLabel: string
{
    case Vegan = 'vegan';
    case Vegetarian = 'vegetarian';
    case GlutenFree = 'gluten_free';
    case LactoseFree = 'lactose_free';
    case Halal = 'halal';
    case Spicy = 'spicy';
    case Organic = 'organic';
    case SugarFree = 'sugar_free';

    public function label(?string $locale = null): string
    {
        return __("menu.dietary.{$this->value}", [], $locale);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
