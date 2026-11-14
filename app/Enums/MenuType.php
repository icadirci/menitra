<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Beverages = 'beverages';
    case Desserts = 'desserts';
    case AllDay = 'all_day';
    case Other = 'other';
}
