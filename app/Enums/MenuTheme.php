<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuTheme: string
{
    case Classic = 'classic';
    case Minimal = 'minimal';
    case Bistro = 'bistro';
    case Cafe = 'cafe';
    case Grid = 'grid';
}
