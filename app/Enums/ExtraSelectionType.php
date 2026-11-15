<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExtraSelectionType: string implements HasLabel
{
    case Single = 'single';
    case Multiple = 'multiple';

    public function getLabel(): string
    {
        return __("menu.selection_types.{$this->value}");
    }
}
