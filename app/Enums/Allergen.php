<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The 14 allergens of the EU / Turkish Food Codex (menu_item_allergens.allergen).
 */
enum Allergen: string
{
    case Gluten = 'gluten';
    case Crustaceans = 'crustaceans';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Peanuts = 'peanuts';
    case Soybeans = 'soybeans';
    case Milk = 'milk';
    case Nuts = 'nuts';
    case Celery = 'celery';
    case Mustard = 'mustard';
    case Sesame = 'sesame';
    case Sulphites = 'sulphites';
    case Lupin = 'lupin';
    case Molluscs = 'molluscs';

    public function label(?string $locale = null): string
    {
        return __("menu.allergens.{$this->value}", [], $locale);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
