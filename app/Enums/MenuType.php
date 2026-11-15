<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MenuType: string implements HasLabel
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Beverages = 'beverages';
    case Desserts = 'desserts';
    case AllDay = 'all_day';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("menu.types.{$this->value}");
    }
}
