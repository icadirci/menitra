<?php

declare(strict_types=1);

namespace App\Filament\Admin\Navigation;

use Filament\Support\Contracts\HasLabel;

enum AdminNavigationGroup implements HasLabel
{
    case Subscriptions;
    case Website;

    public function getLabel(): string
    {
        return match ($this) {
            self::Subscriptions => __('panels.admin.navigation.subscriptions'),
            self::Website => __('panels.admin.navigation.website'),
        };
    }
}
